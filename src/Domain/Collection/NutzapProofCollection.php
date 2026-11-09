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

    // Deliberate: a mint spends a proof once by its secret, so two tags naming one secret are one proof however they are spelled — see nostr-adrs ADR-0131
    public function repeatsASecret(): bool
    {
        $secrets = array_filter(
            $this->mapItems(static fn (NutzapProof $proof): ?string => $proof->getSecret()),
            static fn (?string $secret): bool => null !== $secret,
        );

        return count($secrets) !== count(array_unique($secrets, SORT_STRING));
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, static fn (mixed $value): ?NutzapProof => is_string($value) ? NutzapProof::tryFromJson($value) : null);
    }
}
