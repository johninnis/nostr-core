<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
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

    public function toJsonArray(): array
    {
        return array_map(static fn (EventReference $reference) => $reference->toArray(), $this->items);
    }
}
