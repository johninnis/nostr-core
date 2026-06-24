<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Override;

/**
 * @extends TypedCollection<RelayReference>
 */
final class RelayReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return RelayReference::class;
    }

    public static function empty(): self
    {
        return new self();
    }
}
