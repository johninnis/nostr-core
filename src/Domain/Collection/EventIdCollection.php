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

    public function unique(): self
    {
        return new self($this->deduplicate(static fn (EventId $eventId): string => $eventId->toHex()));
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return $this->mapItems(static fn (EventId $eventId): string => $eventId->toHex());
    }
}
