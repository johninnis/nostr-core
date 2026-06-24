<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use JsonSerializable;
use Override;

/**
 * @extends TypedCollection<Event>
 */
final class EventCollection extends TypedCollection implements JsonSerializable
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
        return new self(array_filter(
            $this->items,
            static fn (Event $event) => !$event->getId()->equals($eventId)
        ));
    }

    public function contains(EventId $eventId): bool
    {
        return array_any($this->items, static fn (Event $event): bool => $event->getId()->equals($eventId));
    }

    public function filterByKind(EventKind $kind): self
    {
        return new self(array_filter(
            $this->items,
            static fn (Event $event) => $event->getKind()->equals($kind)
        ));
    }

    public function filterByAuthor(PublicKey $author): self
    {
        return new self(array_filter(
            $this->items,
            static fn (Event $event) => $event->getPubkey()->equals($author)
        ));
    }

    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->items, $predicate));
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
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

    public function last(): ?Event
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    public function toJsonArray(): array
    {
        return array_map(static fn (Event $event) => $event->toArray(), $this->items);
    }

    public function merge(self $other): self
    {
        return new self([...$this->items, ...$other->items]);
    }

    public function unique(): self
    {
        return new self($this->deduplicate(static fn (Event $event): string => (string) $event->getId()));
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toJsonArray();
    }
}
