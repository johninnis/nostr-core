<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Override;

/**
 * @extends KeyedCollection<Event>
 */
final class EventCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Event::class;
    }

    public function add(Event $event): self
    {
        return new self([...$this->items, $event]);
    }

    public function remove(EventId $eventId): self
    {
        return new self(array_values(array_filter(
            $this->items,
            static fn (Event $event): bool => !$event->getId()->equals($eventId)
        )));
    }

    /**
     * @param callable(Event): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    public function sortByTimestamp(bool $ascending = true): self
    {
        $events = $this->items;
        usort($events, static function (Event $a, Event $b) use ($ascending) {
            $comparison = $a->getCreatedAt()->compareTo($b->getCreatedAt());

            return $ascending ? $comparison : -$comparison;
        });

        return new self($events);
    }

    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length));
    }

    public function first(): ?Event
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (Event $event): array => $event->toArray());
    }
}
