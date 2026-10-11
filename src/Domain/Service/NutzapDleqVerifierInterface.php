<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\MintKeysetCollection;
use Innis\Nostr\Core\Domain\Failure\NutzapDleqFailure;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;

interface NutzapDleqVerifierInterface
{
    // Deliberate: a second verification after NutzapVerifierInterface's, over the proofs the read nutzap carries, as only it needs the mint's keysets — see nostr-adrs ADR-0121
    public function verify(Nutzap $nutzap, MintKeysetCollection $keysets): ?NutzapDleqFailure;
}
