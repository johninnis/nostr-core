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

    private static function keyOf(EventId $eventId): string
    {
        return $eventId->toHex();
    }

    private static function tryParse(mixed $value): ?EventId
    {
        return is_string($value) ? EventId::fromHex($value) : null;
    }

    public static function fromHexValues(mixed $values): self
    {
        return new self(self::parseEach($values, self::tryParse(...)));
    }

    public static function fromWire(mixed $values): ?self
    {
        $eventIds = self::parseEachStrict($values, self::tryParse(...));

        return null === $eventIds ? null : new self($eventIds);
    }

    public function unique(): self
    {
        return new self($this->deduplicate(self::keyOf(...)));
    }

    public function contains(EventId $eventId): bool
    {
        return $this->containsByKey(self::keyOf($eventId), self::keyOf(...));
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return $this->mapItems(self::keyOf(...));
    }
}
