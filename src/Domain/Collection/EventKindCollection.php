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

    private static function keyOf(EventKind $eventKind): int
    {
        return $eventKind->toInt();
    }

    public static function fromInts(mixed $values): self
    {
        return new self(self::parseInts($values, EventKind::tryFromInt(...)));
    }

    public static function fromWire(mixed $values): ?self
    {
        $kinds = self::parseIntsStrict($values, EventKind::tryFromInt(...));

        return null === $kinds ? null : new self($kinds);
    }

    /**
     * @return list<int>
     */
    public function toInts(): array
    {
        return $this->mapItems(self::keyOf(...));
    }

    public function contains(EventKind $eventKind): bool
    {
        return $this->containsByKey(self::keyOf($eventKind), self::keyOf(...));
    }

    public function intersect(self $other): self
    {
        return new self($this->retainByKey($other, self::keyOf(...), true));
    }

    public function diff(self $other): self
    {
        return new self($this->retainByKey($other, self::keyOf(...), false));
    }
}
