<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;

interface CashuDleqVerifierInterface
{
    public function isValid(NutzapProof $proof, string $mintKey): bool;
}
