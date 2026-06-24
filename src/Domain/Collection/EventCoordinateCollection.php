<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Override;

/**
 * @extends TypedCollection<EventCoordinate>
 */
final class EventCoordinateCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventCoordinate::class;
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function fromArrays(mixed $values): self
    {
        return new self(self::parseArrays($values, EventCoordinate::fromArray(...)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (EventCoordinate $coordinate): array => $coordinate->toArray());
    }
}
