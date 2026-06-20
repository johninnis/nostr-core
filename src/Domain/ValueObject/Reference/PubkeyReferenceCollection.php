<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
use Override;

/**
 * @extends TypedCollection<PubkeyReference>
 */
final class PubkeyReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return PubkeyReference::class;
    }

    /**
     * @return list<PubkeyReference>
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
