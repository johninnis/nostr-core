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

    // Deliberate: a body that is not a JSON object is no answer, not a verdict, so it is FetchFailed and can be checked again — see ADR-0090
    public static function verify(string $document, Nip05Identifier $identifier, PublicKey $expectedPubkey): ?Nip05VerificationFailure
    {
        $fields = JsonWireFormat::decodeObject($document);

        if (null === $fields) {
            return Nip05VerificationFailure::FetchFailed;
        }

        if (!isset($fields['names'])) {
            return Nip05VerificationFailure::MissingNames;
        }

        $returnedPubkey = JsonWireFormat::objectFields($fields['names'])[$identifier->getLocalPart()] ?? null;

        return match (true) {
            null === $returnedPubkey => Nip05VerificationFailure::NameNotFound,
            $returnedPubkey !== $expectedPubkey->toHex() => Nip05VerificationFailure::PubkeyMismatch,
            default => null,
        };
    }
}
