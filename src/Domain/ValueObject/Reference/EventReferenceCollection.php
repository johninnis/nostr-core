<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
use Override;

/**
 * @extends TypedCollection<EventReference>
 */
final class EventReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventReference::class;
    }

    public static function empty(): self
    {
        return new self();
    }
}
