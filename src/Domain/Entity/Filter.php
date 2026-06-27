<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use stdClass;
use Stringable;

final readonly class Filter implements JsonSerializable, Stringable
{
    public const int MAX_VALUES_PER_FIELD = 1000;

    /** @var array<string, array<string, int>>|null */
    private ?array $tagValueSets;
    /** @var list<string>|null */
    private ?array $searchTerms;

    /**
     * @param array<string, list<string>>|null $tags
     */
    public function __construct(
        private ?EventIdCollection $ids = null,
        private ?PublicKeyCollection $authors = null,
        private ?EventKindCollection $kinds = null,
        private ?array $tags = null,
        private ?Timestamp $since = null,
        private ?Timestamp $until = null,
        private ?int $limit = null,
        private ?string $search = null,
    ) {
        if (!self::isValidLimit($this->limit)) {
            throw new InvalidArgumentException('Limit must be between 1 and 5000');
        }

        if (null !== $this->since && null !== $this->until && $this->since->isAfter($this->until)) {
            throw new InvalidArgumentException('Since timestamp cannot be after until timestamp');
        }

        self::assertCountWithinCap('ids', $this->ids?->count());
        self::assertCountWithinCap('authors', $this->authors?->count());
        self::assertCountWithinCap('kinds', $this->kinds?->count());

        if (null !== $this->tags) {
            foreach ($this->tags as $tagName => $values) {
                self::assertCountWithinCap("#{$tagName}", count($values));
            }
        }

        $this->tagValueSets = null === $this->tags ? null : array_map(self::flipStrings(...), $this->tags);
        $this->searchTerms = null === $this->search
            ? null
            : (preg_split('/\s+/', mb_strtolower(trim($this->search)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, int>
     */
    private static function flipStrings(array $values): array
    {
        return array_flip(array_filter($values, is_string(...)));
    }

    private static function assertCountWithinCap(string $fieldName, ?int $count): void
    {
        if (null !== $count && $count > self::MAX_VALUES_PER_FIELD) {
            throw new InvalidArgumentException(sprintf('Filter field "%s" may contain at most %d values', $fieldName, self::MAX_VALUES_PER_FIELD));
        }
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

        if (null !== $this->tagValueSets && !$this->matchesTags($event)) {
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

    /**
     * @return array<string, list<string>>|null
     */
    public function getTags(): ?array
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
            foreach ($this->tags as $tagName => $values) {
                $filter["#{$tagName}"] = $values;
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
        $tags = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, '#')) {
                continue;
            }

            $tagName = substr($key, 1);

            if ('' === $tagName || !is_array($value)) {
                return null;
            }

            $tags[$tagName] = array_values(array_filter($value, is_string(...)));
            unset($data[$key]);
        }

        $ids = $data['ids'] ?? null;
        $authors = $data['authors'] ?? null;
        $kinds = $data['kinds'] ?? null;
        $since = $data['since'] ?? null;
        $until = $data['until'] ?? null;
        $limit = $data['limit'] ?? null;
        $search = $data['search'] ?? null;

        if ((null !== $ids && !is_array($ids))
            || (null !== $authors && !is_array($authors))
            || (null !== $kinds && !is_array($kinds))
            || (null !== $since && !is_int($since))
            || (null !== $until && !is_int($until))
            || (null !== $limit && !is_int($limit))
            || (null !== $search && !is_string($search))
        ) {
            return null;
        }

        $idCollection = null;
        if (null !== $ids) {
            $eventIds = [];
            foreach ($ids as $id) {
                $eventId = is_string($id) ? EventId::fromHex($id) : null;
                if (null === $eventId) {
                    return null;
                }
                $eventIds[] = $eventId;
            }
            $idCollection = new EventIdCollection($eventIds);
        }

        $authorCollection = null;
        if (null !== $authors) {
            $publicKeys = [];
            foreach ($authors as $author) {
                $publicKey = is_string($author) ? PublicKey::fromHex($author) : null;
                if (null === $publicKey) {
                    return null;
                }
                $publicKeys[] = $publicKey;
            }
            $authorCollection = new PublicKeyCollection($publicKeys);
        }

        $kindCollection = null;
        if (null !== $kinds) {
            $kindObjects = [];
            foreach ($kinds as $kind) {
                $eventKind = is_int($kind) ? EventKind::tryFromInt($kind) : null;
                if (null === $eventKind) {
                    return null;
                }
                $kindObjects[] = $eventKind;
            }
            $kindCollection = new EventKindCollection($kindObjects);
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

        if (null !== $sinceTimestamp && null !== $untilTimestamp && $sinceTimestamp->isAfter($untilTimestamp)) {
            return null;
        }

        if (!self::isValidLimit($limit)
            || ($idCollection?->count() ?? 0) > self::MAX_VALUES_PER_FIELD
            || ($authorCollection?->count() ?? 0) > self::MAX_VALUES_PER_FIELD
            || ($kindCollection?->count() ?? 0) > self::MAX_VALUES_PER_FIELD
        ) {
            return null;
        }

        if (!array_all($tags, static fn (array $values): bool => count($values) <= self::MAX_VALUES_PER_FIELD)) {
            return null;
        }

        return new self(
            $idCollection,
            $authorCollection,
            $kindCollection,
            [] === $tags ? null : $tags,
            $sinceTimestamp,
            $untilTimestamp,
            $limit,
            $search
        );
    }

    private function matchesTags(Event $event): bool
    {
        foreach ($this->tagValueSets ?? [] as $tagName => $valueSet) {
            if (!$this->eventMatchesTagFilter($event, (string) $tagName, $valueSet)) {
                return false;
            }
        }

        return true;
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

    /**
     * @param array<string, int> $valueSet
     */
    private function eventMatchesTagFilter(Event $event, string $tagName, array $valueSet): bool
    {
        foreach ($event->getTags()->findByName($tagName) as $eventTag) {
            $value = $eventTag->getValue();

            if (null !== $value && isset($valueSet[$value])) {
                return true;
            }
        }

        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return json_encode($this->jsonSerialize(), JsonWireFormat::MESSAGE);
    }
}
