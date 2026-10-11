<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\NutzapVerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapInfo;

interface NutzapVerifierInterface
{
    // Deliberate: verifies only what the two events establish; DLEQ against the mint's keysets is NutzapDleqVerifierInterface's, and neither event's signature is checked here — see nostr-adrs ADR-0121
    public function verify(Nutzap $nutzap, NutzapInfo $recipientsInfo): ?NutzapVerificationFailure;
}
