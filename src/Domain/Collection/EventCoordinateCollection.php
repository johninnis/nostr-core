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

    public function toJsonArray(): array
    {
        return array_map(static fn (EventCoordinate $coordinate) => $coordinate->toArray(), $this->items);
    }
}
