<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Port\Nip05VerificationServiceInterface;
use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailureReason;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05VerificationResult;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final class Nip05Verifier implements Nip05VerificationServiceInterface
{
    public function __construct(
        private readonly HttpServiceInterface $httpService,
    ) {
    }

    #[Override]
    public function verify(Nip05Identifier $identifier, PublicKey $expectedPubkey): Nip05VerificationResult
    {
        $wellKnownUrl = $identifier->getWellKnownUrl();

        $data = $this->httpService->getJson($wellKnownUrl, [
            'Accept' => 'application/json',
            'User-Agent' => 'Nostr-PHP/1.0',
        ]);

        if (null === $data) {
            return Nip05VerificationResult::failure($identifier, $expectedPubkey, Nip05VerificationFailureReason::FetchFailed);
        }

        if (!isset($data['names'])) {
            return Nip05VerificationResult::failure($identifier, $expectedPubkey, Nip05VerificationFailureReason::MissingNames);
        }

        $localPart = $identifier->getLocalPart();
        if (!isset($data['names'][$localPart])) {
            return Nip05VerificationResult::failure($identifier, $expectedPubkey, Nip05VerificationFailureReason::NameNotFound);
        }

        $returnedPubkey = $data['names'][$localPart];
        $expectedHex = $expectedPubkey->toHex();

        if ($returnedPubkey !== $expectedHex) {
            return Nip05VerificationResult::failure($identifier, $expectedPubkey, Nip05VerificationFailureReason::PubkeyMismatch);
        }

        return Nip05VerificationResult::success($identifier, $expectedPubkey);
    }
}
