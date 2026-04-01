<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;

final readonly class Filter
{
    public function __construct(
        private ?array $ids = null,
        private ?array $authors = null,
        private ?array $kinds = null,
        private ?array $tags = null,
        private ?Timestamp $since = null,
        private ?Timestamp $until = null,
        private ?int $limit = null,
        private ?string $search = null,
    ) {
        if (null !== $this->limit && ($this->limit < 1 || $this->limit > 5000)) {
            throw new InvalidArgumentException('Limit must be between 1 and 5000');
        }

        if (null !== $this->since && null !== $this->until && $this->since->isAfter($this->until)) {
            throw new InvalidArgumentException('Since timestamp cannot be after until timestamp');
        }
    }

    public function matches(Event $event): bool
    {
        if (null !== $this->ids && !$this->matchesIds($event)) {
            return false;
        }

        if (null !== $this->authors && !$this->matchesAuthors($event)) {
            return false;
        }

        if (null !== $this->kinds && !$this->matchesKinds($event)) {
            return false;
        }

        if (null !== $this->tags && !$this->matchesTags($event)) {
            return false;
        }

        if (null !== $this->since && $event->getCreatedAt()->isBefore($this->since)) {
            return false;
        }

        if (null !== $this->until && $event->getCreatedAt()->isAfter($this->until)) {
            return false;
        }

        if (null !== $this->search && !$this->matchesSearch($event)) {
            return false;
        }

        return true;
    }

    public function getIds(): ?array
    {
        return $this->ids;
    }

    public function hasIds(): bool
    {
        return null !== $this->ids;
    }

    public function getAuthors(): ?array
    {
        return $this->authors;
    }

    public function hasAuthors(): bool
    {
        return null !== $this->authors;
    }

    public function getKinds(): ?array
    {
        return $this->kinds;
    }

    public function hasKinds(): bool
    {
        return null !== $this->kinds;
    }

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

    public function withAuthors(array $authors): self
    {
        return new self(
            $this->ids,
            $authors,
            $this->kinds,
            $this->tags,
            $this->since,
            $this->until,
            $this->limit,
            $this->search
        );
    }

    public function withKinds(array $kinds): self
    {
        return new self(
            $this->ids,
            $this->authors,
            $kinds,
            $this->tags,
            $this->since,
            $this->until,
            $this->limit,
            $this->search
        );
    }

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
            $filter['kinds'] = array_map(
                static fn ($kind) => $kind instanceof EventKind ? $kind->toInt() : $kind,
                $this->kinds
            );
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

    public static function fromArray(array $data): self
    {
        $tags = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, '#') && is_array($value)) {
                $tags[substr($key, 1)] = $value;
                unset($data[$key]);
            }
        }

        return new self(
            $data['ids'] ?? null,
            $data['authors'] ?? null,
            $data['kinds'] ?? null,
            empty($tags) ? null : $tags,
            isset($data['since']) ? Timestamp::fromInt($data['since']) : null,
            isset($data['until']) ? Timestamp::fromInt($data['until']) : null,
            $data['limit'] ?? null,
            $data['search'] ?? null
        );
    }

    private function matchesIds(Event $event): bool
    {
        return null === $this->ids || in_array($event->getId()->toHex(), $this->ids, true);
    }

    private function matchesAuthors(Event $event): bool
    {
        return null === $this->authors || in_array($event->getPubkey()->toHex(), $this->authors, true);
    }

    private function matchesKinds(Event $event): bool
    {
        return null === $this->kinds || in_array($event->getKind()->toInt(), $this->kinds, true);
    }

    private function matchesTags(Event $event): bool
    {
        if (null === $this->tags) {
            return true;
        }

        foreach ($this->tags as $tagName => $values) {
            if (!$this->eventMatchesTagFilter($event, $tagName, $values)) {
                return false;
            }
        }

        return true;
    }

    private function matchesSearch(Event $event): bool
    {
        $terms = preg_split('/\s+/', mb_strtolower(trim($this->search ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($terms)) {
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

    private function eventMatchesTagFilter(Event $event, string $tagName, array $filterValues): bool
    {
        $eventTags = $event->getTags()->findByType(TagType::fromString($tagName));

        foreach ($eventTags as $eventTag) {
            foreach ($filterValues as $filterValue) {
                if ($eventTag->hasValue($filterValue)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return json_encode($this->toArray()) ?: '';
    }
}
