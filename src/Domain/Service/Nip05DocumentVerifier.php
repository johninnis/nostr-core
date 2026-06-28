<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

final class Nip05DocumentVerifier
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function verify(array $document, Nip05Identifier $identifier, PublicKey $expectedPubkey): ?Nip05VerificationFailure
    {
        if (!isset($document['names'])) {
            return Nip05VerificationFailure::MissingNames;
        }

        $names = $document['names'];
        $localPart = $identifier->getLocalPart();

        if (!is_array($names) || !isset($names[$localPart])) {
            return Nip05VerificationFailure::NameNotFound;
        }

        $returnedPubkey = $names[$localPart];

        if (!is_string($returnedPubkey) || 0 !== strcasecmp($returnedPubkey, $expectedPubkey->toHex())) {
            return Nip05VerificationFailure::PubkeyMismatch;
        }

        return null;
    }
}
