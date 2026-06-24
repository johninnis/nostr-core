<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
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

    public static function empty(): self
    {
        return new self();
    }

    public function toJsonArray(): array
    {
        return array_map(static fn (PubkeyReference $reference) => $reference->toArray(), $this->items);
    }
}
