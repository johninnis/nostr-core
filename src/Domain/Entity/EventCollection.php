<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use ArrayIterator;
use Countable;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;

final class EventCollection implements IteratorAggregate, Countable, JsonSerializable
{
    private array $events = [];

    public function __construct(array $events = [])
    {
        foreach ($events as $event) {
            if (!$event instanceof Event) {
                throw new InvalidArgumentException('All items must be Event instances');
            }
        }
        $this->events = array_values($events);
    }

    public function add(Event $event): self
    {
        $events = $this->events;
        $events[] = $event;

        return new self($events);
    }

    public function remove(EventId $eventId): self
    {
        $events = array_filter(
            $this->events,
            static fn (Event $event) => !$event->getId()->equals($eventId)
        );

        return new self($events);
    }

    public function contains(EventId $eventId): bool
    {
        foreach ($this->events as $event) {
            if ($event->getId()->equals($eventId)) {
                return true;
            }
        }

        return false;
    }

    public function filterByKind(EventKind $kind): self
    {
        $filtered = array_filter(
            $this->events,
            static fn (Event $event) => $event->getKind()->equals($kind)
        );

        return new self($filtered);
    }

    public function filterByAuthor(PublicKey $author): self
    {
        $filtered = array_filter(
            $this->events,
            static fn (Event $event) => $event->getPubkey()->equals($author)
        );

        return new self($filtered);
    }

    public function filter(callable $predicate): self
    {
        $filtered = array_filter($this->events, $predicate);

        return new self($filtered);
    }

    public function map(callable $callback): self
    {
        $mapped = array_map($callback, $this->events);

        return new self($mapped);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->events, $callback, $initial);
    }

    public function sortByTimestamp(bool $ascending = true): self
    {
        $events = $this->events;
        usort($events, static function (Event $a, Event $b) use ($ascending) {
            $comparison = $a->getCreatedAt()->compareTo($b->getCreatedAt());

            return $ascending ? $comparison : -$comparison;
        });

        return new self($events);
    }

    public function slice(int $offset, ?int $length = null): self
    {
        $sliced = array_slice($this->events, $offset, $length);

        return new self($sliced);
    }

    public function first(): ?Event
    {
        return $this->events[0] ?? null;
    }

    public function last(): ?Event
    {
        return $this->events[count($this->events) - 1] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->events);
    }

    public function toArray(): array
    {
        return $this->events;
    }

    public function toJsonArray(): array
    {
        return array_map(static fn (Event $event) => $event->toArray(), $this->events);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->events, $other->events));
    }

    public function unique(): self
    {
        $seen = [];
        $unique = [];

        foreach ($this->events as $event) {
            $id = (string) $event->getId();
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $event;
            }
        }

        return new self($unique);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->events);
    }

    public function count(): int
    {
        return count($this->events);
    }

    public function jsonSerialize(): array
    {
        return $this->toJsonArray();
    }
}
