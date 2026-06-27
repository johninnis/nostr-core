<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface Nip05VerifierInterface
{
    public function verify(Nip05Identifier $identifier, PublicKey $expectedPubkey): ?Nip05VerificationFailure;
}
