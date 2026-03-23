<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class Filter
{
    public function __construct(
        private ?array $ids = null,
        private ?array $authors = null,
        private ?array $kinds = null,
        private ?array $tags = null,
        private ?Timestamp $since = null,
        private ?Timestamp $until = null,
        private ?int $limit = null
    ) {
        if ($this->limit !== null && ($this->limit < 1 || $this->limit > 5000)) {
            throw new \InvalidArgumentException('Limit must be between 1 and 5000');
        }

        if ($this->since !== null && $this->until !== null && $this->since->isAfter($this->until)) {
            throw new \InvalidArgumentException('Since timestamp cannot be after until timestamp');
        }
    }

    public function matches(Event $event): bool
    {
        if ($this->ids !== null && !$this->matchesIds($event)) {
            return false;
        }

        if ($this->authors !== null && !$this->matchesAuthors($event)) {
            return false;
        }

        if ($this->kinds !== null && !$this->matchesKinds($event)) {
            return false;
        }

        if ($this->tags !== null && !$this->matchesTags($event)) {
            return false;
        }

        if ($this->since !== null && $event->getCreatedAt()->isBefore($this->since)) {
            return false;
        }

        if ($this->until !== null && $event->getCreatedAt()->isAfter($this->until)) {
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
        return $this->ids !== null;
    }

    public function getAuthors(): ?array
    {
        return $this->authors;
    }

    public function hasAuthors(): bool
    {
        return $this->authors !== null;
    }

    public function getKinds(): ?array
    {
        return $this->kinds;
    }

    public function hasKinds(): bool
    {
        return $this->kinds !== null;
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
        return $this->limit !== null;
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
            $this->limit
        );
    }

    public function toArray(): array
    {
        $filter = [];

        if ($this->ids !== null) {
            $filter['ids'] = $this->ids;
        }

        if ($this->authors !== null) {
            $filter['authors'] = $this->authors;
        }

        if ($this->kinds !== null) {
            $filter['kinds'] = array_map(
                fn ($kind) => $kind instanceof EventKind ? $kind->toInt() : $kind,
                $this->kinds
            );
        }

        if ($this->tags !== null) {
            foreach ($this->tags as $tagName => $values) {
                $filter["#{$tagName}"] = $values;
            }
        }

        if ($this->since !== null) {
            $filter['since'] = $this->since->toInt();
        }

        if ($this->until !== null) {
            $filter['until'] = $this->until->toInt();
        }

        if ($this->limit !== null) {
            $filter['limit'] = $this->limit;
        }

        return $filter;
    }

    public static function fromArray(array $data): self
    {
        $tags = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, '#') && \is_array($value)) {
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
            $data['limit'] ?? null
        );
    }

    private function matchesIds(Event $event): bool
    {
        return $this->ids === null || \in_array($event->getId()->toHex(), $this->ids, true);
    }

    private function matchesAuthors(Event $event): bool
    {
        return $this->authors === null || \in_array($event->getPubkey()->toHex(), $this->authors, true);
    }

    private function matchesKinds(Event $event): bool
    {
        return $this->kinds === null || \in_array($event->getKind()->toInt(), $this->kinds, true);
    }

    private function matchesTags(Event $event): bool
    {
        if ($this->tags === null) {
            return true;
        }

        foreach ($this->tags as $tagName => $values) {
            if (!$this->eventMatchesTagFilter($event, $tagName, $values)) {
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
