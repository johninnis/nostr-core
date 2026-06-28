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

    private static function tryParse(mixed $value): ?EventCoordinate
    {
        return is_array($value) ? EventCoordinate::fromArray($value) : null;
    }

    public static function fromArrays(mixed $values): self
    {
        return new self(self::parseEach($values, self::tryParse(...)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (EventCoordinate $coordinate): array => $coordinate->toArray());
    }
}
