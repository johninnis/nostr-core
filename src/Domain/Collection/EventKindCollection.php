<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Override;

/**
 * @extends TypedCollection<EventKind>
 */
final class EventKindCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventKind::class;
    }

    /**
     * @return list<int>
     */
    public function toInts(): array
    {
        return array_map(static fn (EventKind $kind): int => $kind->toInt(), $this->items);
    }

    public function contains(EventKind $eventKind): bool
    {
        return array_any($this->items, static fn (EventKind $candidate): bool => $candidate->equals($eventKind));
    }

    public function intersect(self $other): self
    {
        return new self($this->retainByKey($other, static fn (EventKind $eventKind): string => (string) $eventKind->toInt(), true));
    }

    public function diff(self $other): self
    {
        return new self($this->retainByKey($other, static fn (EventKind $eventKind): string => (string) $eventKind->toInt(), false));
    }
}
