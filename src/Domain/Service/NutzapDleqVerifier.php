<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\MintKeysetCollection;
use Innis\Nostr\Core\Domain\Failure\NutzapDleqFailure;
use Innis\Nostr\Core\Domain\ValueObject\Payment\MintKeyset;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Override;

final readonly class NutzapDleqVerifier implements NutzapDleqVerifierInterface
{
    public function __construct(private CashuDleqVerifierInterface $cashuDleqVerifier)
    {
    }

    #[Override]
    public function verify(Nutzap $nutzap, MintKeysetCollection $keysets): ?NutzapDleqFailure
    {
        $proofs = $nutzap->getProofs()->toArray();
        $unit = $nutzap->getCashuAmount()?->getUnit();

        return match (true) {
            [] === $proofs => NutzapDleqFailure::NoProof,
            !array_all($proofs, static fn (NutzapProof $proof): bool => null !== self::keysetOf($proof, $keysets)) => NutzapDleqFailure::UnknownKeyset,
            // Deliberate: a keyset's unit is compared exactly with the nutzap's, as its amounts are in that unit and a misstated unit tag would show a false amount — see nostr-adrs ADR-0121
            !array_all($proofs, static fn (NutzapProof $proof): bool => self::keysetOf($proof, $keysets)?->getUnit() === $unit) => NutzapDleqFailure::UnitMismatch,
            !array_all($proofs, static fn (NutzapProof $proof): bool => null !== self::mintKeyOf($proof, $keysets)) => NutzapDleqFailure::NoMintKey,
            !array_all($proofs, fn (NutzapProof $proof): bool => $this->hasValidDleq($proof, $keysets)) => NutzapDleqFailure::DleqInvalid,
            default => null,
        };
    }

    private function hasValidDleq(NutzapProof $proof, MintKeysetCollection $keysets): bool
    {
        $mintKey = self::mintKeyOf($proof, $keysets);

        return null !== $mintKey && $this->cashuDleqVerifier->isValid($proof, $mintKey);
    }

    private static function mintKeyOf(NutzapProof $proof, MintKeysetCollection $keysets): ?string
    {
        return self::keysetOf($proof, $keysets)?->getKeyFor($proof->getAmount());
    }

    private static function keysetOf(NutzapProof $proof, MintKeysetCollection $keysets): ?MintKeyset
    {
        $keysetId = $proof->getKeysetId();

        return null === $keysetId ? null : $keysets->findById($keysetId);
    }
}
