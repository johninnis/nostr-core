<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
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

    /**
     * @return list<ContentReference>
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
