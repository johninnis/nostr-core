<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
use Override;

/**
 * @extends TypedCollection<RelayUrl>
 */
final class RelayUrlCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return RelayUrl::class;
    }
}
