<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Override;

/**
 * @extends TypedCollection<NutzapProof>
 */
final class NutzapProofCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return NutzapProof::class;
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, static fn (mixed $value): ?NutzapProof => is_string($value) ? NutzapProof::tryFromJson($value) : null);
    }
}
