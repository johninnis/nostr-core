<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\NutzapVerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapInfo;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Override;

final readonly class NutzapVerifier implements NutzapVerifierInterface
{
    #[Override]
    public function verify(Nutzap $nutzap, NutzapInfo $recipientsInfo): ?NutzapVerificationFailure
    {
        $mint = $nutzap->getMint();
        $proofs = $nutzap->getProofs()->toArray();

        return match (true) {
            true !== $nutzap->getRecipientPubkey()?->equals($recipientsInfo->getPubkey()) => NutzapVerificationFailure::RecipientMismatch,
            // Deliberate: the mint is compared exactly as written, never normalised, as the recipient's #u filter matches it — see nostr-adrs ADR-0121
            null === $mint || !$recipientsInfo->getMints()->contains($mint) => NutzapVerificationFailure::MintNotListed,
            [] === $proofs => NutzapVerificationFailure::NoProof,
            !array_all($proofs, static fn (NutzapProof $proof): bool => $proof->isLockedTo($recipientsInfo->getP2pkPubkey())) => NutzapVerificationFailure::ProofNotLockedToRecipient,
            default => null,
        };
    }
}
