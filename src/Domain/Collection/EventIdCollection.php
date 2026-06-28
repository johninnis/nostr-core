<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Override;

/**
 * @extends TypedCollection<EventId>
 */
final class EventIdCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventId::class;
    }

    public static function fromHexValues(mixed $values): self
    {
        return new self(self::parseStrings($values, EventId::fromHex(...)));
    }

    public static function fromWire(mixed $values): ?self
    {
        $eventIds = self::parseStringsStrict($values, EventId::fromHex(...));

        return null === $eventIds ? null : new self($eventIds);
    }

    public function unique(): self
    {
        return new self($this->deduplicate(static fn (EventId $eventId): string => $eventId->toHex()));
    }

    public function contains(EventId $eventId): bool
    {
        return $this->containsByKey($eventId->toHex(), static fn (EventId $id): string => $id->toHex());
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return $this->mapItems(static fn (EventId $eventId): string => $eventId->toHex());
    }
}
