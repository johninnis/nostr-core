<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Override;

/**
 * @extends TypedCollection<ContentReference>
 */
final class ContentReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return ContentReference::class;
    }
}
