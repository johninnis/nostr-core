<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use stdClass;
use Stringable;

final readonly class Filter implements JsonSerializable, Stringable
{
    public const int MAX_VALUES_PER_FIELD = 1000;

    /** @var list<EventKind>|null */
    private ?array $kinds;
    /** @var array<string, int>|null */
    private ?array $idSet;
    /** @var array<string, int>|null */
    private ?array $authorSet;
    /** @var array<int, int>|null */
    private ?array $kindSet;
    /** @var array<string, array<string, int>>|null */
    private ?array $tagValueSets;
    /** @var list<string>|null */
    private ?array $searchTerms;

    /**
     * @param array<array-key, mixed>|null     $ids
     * @param array<array-key, mixed>|null     $authors
     * @param array<int|EventKind>|null        $kinds
     * @param array<string, list<string>>|null $tags
     */
    public function __construct(
        private ?array $ids = null,
        private ?array $authors = null,
        ?array $kinds = null,
        private ?array $tags = null,
        private ?Timestamp $since = null,
        private ?Timestamp $until = null,
        private ?int $limit = null,
        private ?string $search = null,
    ) {
        $this->kinds = null !== $kinds ? self::normaliseKinds($kinds) : null;

        if (!self::isValidLimit($this->limit)) {
            throw new InvalidArgumentException('Limit must be between 1 and 5000');
        }

        if (null !== $this->since && null !== $this->until && $this->since->isAfter($this->until)) {
            throw new InvalidArgumentException('Since timestamp cannot be after until timestamp');
        }

        self::assertFieldWithinCap('ids', $this->ids);
        self::assertFieldWithinCap('authors', $this->authors);
        self::assertFieldWithinCap('kinds', $this->kinds);

        if (null !== $this->tags) {
            foreach ($this->tags as $tagName => $values) {
                self::assertFieldWithinCap("#{$tagName}", $values);
            }
        }

        $this->idSet = null !== $this->ids ? self::flipStrings($this->ids) : null;
        $this->authorSet = null !== $this->authors ? self::flipStrings($this->authors) : null;
        $this->kindSet = null !== $this->kinds
            ? array_flip(new EventKindCollection($this->kinds)->toInts())
            : null;
        $this->tagValueSets = null !== $this->tags ? array_map(self::flipStrings(...), $this->tags) : null;
        $this->searchTerms = null !== $this->search
            ? (preg_split('/\s+/', mb_strtolower(trim($this->search)), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : null;
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

    /**
     * @param array<array-key, mixed>|null $values
     */
    private static function assertFieldWithinCap(string $fieldName, ?array $values): void
    {
        if (!self::isWithinCap($values)) {
            throw new InvalidArgumentException(sprintf('Filter field "%s" may contain at most %d values', $fieldName, self::MAX_VALUES_PER_FIELD));
        }
    }

    /**
     * @param array<array-key, mixed>|null $values
     */
    private static function isWithinCap(?array $values): bool
    {
        return null === $values || count($values) <= self::MAX_VALUES_PER_FIELD;
    }

    private static function isValidLimit(?int $limit): bool
    {
        return null === $limit || ($limit >= 1 && $limit <= 5000);
    }

    public function matches(Event $event): bool
    {
        if (null !== $this->idSet && !isset($this->idSet[$event->getId()->toHex()])) {
            return false;
        }

        if (null !== $this->authorSet && !isset($this->authorSet[$event->getPubkey()->toHex()])) {
            return false;
        }

        if (null !== $this->kindSet && !isset($this->kindSet[$event->getKind()->toInt()])) {
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
        return null === $this->ids ? null : EventIdCollection::fromHexValues($this->ids);
    }

    public function hasIds(): bool
    {
        return null !== $this->ids;
    }

    public function getAuthors(): ?PublicKeyCollection
    {
        return null === $this->authors ? null : PublicKeyCollection::fromHexValues($this->authors);
    }

    public function hasAuthors(): bool
    {
        return null !== $this->authors;
    }

    public function getKinds(): ?EventKindCollection
    {
        return null === $this->kinds ? null : new EventKindCollection($this->kinds);
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

    /**
     * @param list<string> $authors
     */
    public function withAuthors(array $authors): self
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

    /**
     * @param array<int|EventKind> $kinds
     */
    public function withKinds(array $kinds): self
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
     * @param array<int|EventKind> $kinds
     *
     * @return list<EventKind>
     */
    private static function normaliseKinds(array $kinds): array
    {
        return array_values(array_map(
            static fn (int|EventKind $kind) => $kind instanceof EventKind ? $kind : EventKind::fromInt($kind),
            $kinds
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filter = [];

        if (null !== $this->ids) {
            $filter['ids'] = $this->ids;
        }

        if (null !== $this->authors) {
            $filter['authors'] = $this->authors;
        }

        if (null !== $this->kinds) {
            $filter['kinds'] = new EventKindCollection($this->kinds)->toInts();
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
            || (null !== $since && !is_int($since))
            || (null !== $until && !is_int($until))
            || (null !== $limit && !is_int($limit))
            || (null !== $search && !is_string($search))
        ) {
            return null;
        }

        if (null !== $kinds && !is_array($kinds)) {
            return null;
        }

        $kindObjects = null;
        if (null !== $kinds) {
            $kindObjects = [];
            foreach ($kinds as $kind) {
                if (!is_int($kind)) {
                    return null;
                }

                $eventKind = EventKind::tryFromInt($kind);
                if (null === $eventKind) {
                    return null;
                }

                $kindObjects[] = $eventKind;
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

        if (null !== $sinceTimestamp && null !== $untilTimestamp && $sinceTimestamp->isAfter($untilTimestamp)) {
            return null;
        }

        if (!self::isValidLimit($limit)
            || !self::isWithinCap($ids)
            || !self::isWithinCap($authors)
            || !self::isWithinCap($kindObjects)
        ) {
            return null;
        }

        if (!array_all($tags, static fn (array $values): bool => self::isWithinCap($values))) {
            return null;
        }

        return new self(
            $ids,
            $authors,
            $kindObjects,
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
