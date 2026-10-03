<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Override;

/**
 * @extends KeyedCollection<EventId>
 */
final class EventIdCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventId::class;
    }

    private static function tryParse(mixed $value): ?EventId
    {
        return is_string($value) ? EventId::tryFromHex($value) : null;
    }

    public static function fromHexValues(mixed $values): self
    {
        return self::fromEach($values, self::tryParse(...));
    }

    public static function tryFromArray(mixed $values): ?self
    {
        return self::tryFromEach($values, self::tryParse(...));
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return $this->mapItems(static fn (EventId $eventId): string => $eventId->toHex());
    }
}
