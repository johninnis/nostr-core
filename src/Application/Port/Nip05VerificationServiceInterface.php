<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05VerificationResult;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface Nip05VerificationServiceInterface
{
    public function verify(Nip05Identifier $identifier, PublicKey $expectedPubkey): Nip05VerificationResult;
}
