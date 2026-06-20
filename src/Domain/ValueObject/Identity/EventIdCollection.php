<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
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

    /**
     * @return list<EventId>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public static function empty(): self
    {
        return new self();
    }
}
