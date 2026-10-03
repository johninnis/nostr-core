<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Override;

/**
 * @extends KeyedCollection<EventKind>
 */
final class EventKindCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventKind::class;
    }

    private static function tryParse(mixed $value): ?EventKind
    {
        return is_int($value) ? EventKind::tryFromInt($value) : null;
    }

    public static function fromInts(mixed $values): self
    {
        return self::fromEach($values, self::tryParse(...));
    }

    public static function tryFromArray(mixed $values): ?self
    {
        return self::tryFromEach($values, self::tryParse(...));
    }

    /**
     * @return list<int>
     */
    public function toInts(): array
    {
        return $this->mapItems(static fn (EventKind $eventKind): int => $eventKind->toInt());
    }
}
