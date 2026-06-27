<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Port\Nip05VerifierInterface;
use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class Nip05Verifier implements Nip05VerifierInterface
{
    public function __construct(
        private HttpServiceInterface $httpService,
    ) {
    }

    #[Override]
    public function verify(Nip05Identifier $identifier, PublicKey $expectedPubkey): ?Nip05VerificationFailure
    {
        $data = $this->httpService->getJson($identifier->getWellKnownUrl(), [
            'Accept' => 'application/json',
            'User-Agent' => UserAgent::DEFAULT,
        ]);

        if (null === $data) {
            return Nip05VerificationFailure::FetchFailed;
        }

        if (!isset($data['names'])) {
            return Nip05VerificationFailure::MissingNames;
        }

        $localPart = $identifier->getLocalPart();
        if (!is_array($data['names']) || !isset($data['names'][$localPart])) {
            return Nip05VerificationFailure::NameNotFound;
        }

        $returnedPubkey = $data['names'][$localPart];

        if (!is_string($returnedPubkey) || 0 !== strcasecmp($returnedPubkey, $expectedPubkey->toHex())) {
            return Nip05VerificationFailure::PubkeyMismatch;
        }

        return null;
    }
}
