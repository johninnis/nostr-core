<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use stdClass;
use Stringable;

final readonly class Filter implements JsonSerializable, Stringable
{
    public const int MAX_VALUES_PER_FIELD = 1000;

    /** @var list<string>|null */
    private ?array $searchTerms;

    public function __construct(
        private ?EventIdCollection $ids = null,
        private ?PublicKeyCollection $authors = null,
        private ?EventKindCollection $kinds = null,
        private ?TagFilter $tags = null,
        private ?Timestamp $since = null,
        private ?Timestamp $until = null,
        private ?int $limit = null,
        private ?string $search = null,
    ) {
        if (!self::isValidLimit($this->limit)) {
            throw new InvalidArgumentException('Limit must be between 1 and 5000');
        }

        if (!self::areTimestampsInOrder($this->since, $this->until)) {
            throw new InvalidArgumentException('Since timestamp cannot be after until timestamp');
        }

        self::assertCountWithinCap('ids', $this->ids?->count());
        self::assertCountWithinCap('authors', $this->authors?->count());
        self::assertCountWithinCap('kinds', $this->kinds?->count());

        $this->searchTerms = null === $this->search
            ? null
            : (preg_split('/\s+/', mb_strtolower(trim($this->search)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private static function assertCountWithinCap(string $fieldName, ?int $count): void
    {
        if (!self::isCountWithinCap($count)) {
            throw new InvalidArgumentException(sprintf('Filter field "%s" may contain at most %d values', $fieldName, self::MAX_VALUES_PER_FIELD));
        }
    }

    private static function isCountWithinCap(?int $count): bool
    {
        return null === $count || $count <= self::MAX_VALUES_PER_FIELD;
    }

    private static function areTimestampsInOrder(?Timestamp $since, ?Timestamp $until): bool
    {
        return null === $since || null === $until || !$since->isAfter($until);
    }

    private static function isValidLimit(?int $limit): bool
    {
        return null === $limit || ($limit >= 1 && $limit <= 5000);
    }

    public function matches(Event $event): bool
    {
        if (null !== $this->ids && !$this->ids->contains($event->getId())) {
            return false;
        }

        if (null !== $this->authors && !$this->authors->contains($event->getPubkey())) {
            return false;
        }

        if (null !== $this->kinds && !$this->kinds->contains($event->getKind())) {
            return false;
        }

        if (null !== $this->tags && !$this->tags->matches($event->getTags())) {
            return false;
        }

        if (null !== $this->since && $event->getCreatedAt()->isBefore($this->since)) {
            return false;
        }

        if (null !== $this->until && $event->getCreatedAt()->isAfter($this->until)) {
            return false;
        }

        if (null !== $this->searchTerms && !$this->matchesSearch($event)) {
            return false;
        }

        return true;
    }

    public function getIds(): ?EventIdCollection
    {
        return $this->ids;
    }

    public function hasIds(): bool
    {
        return null !== $this->ids;
    }

    public function getAuthors(): ?PublicKeyCollection
    {
        return $this->authors;
    }

    public function hasAuthors(): bool
    {
        return null !== $this->authors;
    }

    public function getKinds(): ?EventKindCollection
    {
        return $this->kinds;
    }

    public function hasKinds(): bool
    {
        return null !== $this->kinds;
    }

    public function getTags(): ?TagFilter
    {
        return $this->tags;
    }

    public function getSince(): ?Timestamp
    {
        return $this->since;
    }

    public function getUntil(): ?Timestamp
    {
        return $this->until;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function hasLimit(): bool
    {
        return null !== $this->limit;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function hasSearch(): bool
    {
        return null !== $this->search;
    }

    public function withAuthors(PublicKeyCollection $authors): self
    {
        return new self(
            ids: $this->ids,
            authors: $authors,
            kinds: $this->kinds,
            tags: $this->tags,
            since: $this->since,
            until: $this->until,
            limit: $this->limit,
            search: $this->search,
        );
    }

    public function withKinds(EventKindCollection $kinds): self
    {
        return new self(
            ids: $this->ids,
            authors: $this->authors,
            kinds: $kinds,
            tags: $this->tags,
            since: $this->since,
            until: $this->until,
            limit: $this->limit,
            search: $this->search,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filter = [];

        if (null !== $this->ids) {
            $filter['ids'] = $this->ids->toHexes();
        }

        if (null !== $this->authors) {
            $filter['authors'] = $this->authors->toHexes();
        }

        if (null !== $this->kinds) {
            $filter['kinds'] = $this->kinds->toInts();
        }

        if (null !== $this->tags) {
            foreach ($this->tags->toArray() as $key => $values) {
                $filter[$key] = $values;
            }
        }

        if (null !== $this->since) {
            $filter['since'] = $this->since->toInt();
        }

        if (null !== $this->until) {
            $filter['until'] = $this->until->toInt();
        }

        if (null !== $this->limit) {
            $filter['limit'] = $this->limit;
        }

        if (null !== $this->search) {
            $filter['search'] = $this->search;
        }

        return $filter;
    }

    /**
     * @return array<string, mixed>|stdClass
     */
    #[Override]
    public function jsonSerialize(): array|stdClass
    {
        return $this->toArray() ?: new stdClass();
    }

    public static function fromWire(mixed $value): ?self
    {
        return is_array($value) || $value instanceof stdClass ? self::fromArray($value) : null;
    }

    /**
     * @param array<array-key, mixed>|stdClass $data
     */
    public static function fromArray(array|stdClass $data): ?self
    {
        $data = (array) $data;

        $tags = TagFilter::fromWire($data);
        if (null === $tags) {
            return null;
        }

        $ids = $data['ids'] ?? null;
        $authors = $data['authors'] ?? null;
        $kinds = $data['kinds'] ?? null;
        $since = $data['since'] ?? null;
        $until = $data['until'] ?? null;
        $limit = $data['limit'] ?? null;
        $search = $data['search'] ?? null;

        if ((null !== $since && !is_int($since))
            || (null !== $until && !is_int($until))
            || (null !== $limit && !is_int($limit))
            || (null !== $search && !is_string($search))
        ) {
            return null;
        }

        if (null !== $search && !mb_check_encoding($search, 'UTF-8')) {
            return null;
        }

        $idCollection = null;
        if (null !== $ids) {
            $idCollection = EventIdCollection::fromWire($ids);
            if (null === $idCollection) {
                return null;
            }
        }

        $authorCollection = null;
        if (null !== $authors) {
            $authorCollection = PublicKeyCollection::fromWire($authors);
            if (null === $authorCollection) {
                return null;
            }
        }

        $kindCollection = null;
        if (null !== $kinds) {
            $kindCollection = EventKindCollection::fromWire($kinds);
            if (null === $kindCollection) {
                return null;
            }
        }

        $sinceTimestamp = null;
        if (null !== $since) {
            $sinceTimestamp = Timestamp::tryFromInt($since);
            if (null === $sinceTimestamp) {
                return null;
            }
        }

        $untilTimestamp = null;
        if (null !== $until) {
            $untilTimestamp = Timestamp::tryFromInt($until);
            if (null === $untilTimestamp) {
                return null;
            }
        }

        if (!self::areTimestampsInOrder($sinceTimestamp, $untilTimestamp)
            || !self::isValidLimit($limit)
            || !self::isCountWithinCap($idCollection?->count())
            || !self::isCountWithinCap($authorCollection?->count())
            || !self::isCountWithinCap($kindCollection?->count())
        ) {
            return null;
        }

        return new self(
            $idCollection,
            $authorCollection,
            $kindCollection,
            $tags->isEmpty() ? null : $tags,
            $sinceTimestamp,
            $untilTimestamp,
            $limit,
            $search
        );
    }

    private function matchesSearch(Event $event): bool
    {
        $terms = $this->searchTerms ?? [];

        if ([] === $terms) {
            return true;
        }

        $content = mb_strtolower((string) $event->getContent());

        foreach ($terms as $term) {
            if (!str_contains($content, $term)) {
                return false;
            }
        }

        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return json_encode($this->jsonSerialize(), JsonWireFormat::MESSAGE);
    }
}
