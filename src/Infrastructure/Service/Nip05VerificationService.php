<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Domain\Service\Nip05VerificationServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05VerificationResult;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Psr\Log\LoggerInterface;

final class Nip05VerificationService implements Nip05VerificationServiceInterface
{
    public function __construct(
        private readonly HttpServiceInterface $httpService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function verify(Nip05Identifier $identifier, PublicKey $expectedPubkey): Nip05VerificationResult
    {
        $wellKnownUrl = $identifier->getWellKnownUrl();

        $this->logger->debug('Verifying NIP-05 identifier', [
            'identifier' => (string) $identifier,
            'url' => $wellKnownUrl,
            'expected_pubkey' => $expectedPubkey->toHex(),
        ]);

        $data = $this->httpService->getJson($wellKnownUrl, [
            'Accept' => 'application/json',
            'User-Agent' => 'Nostr-PHP/1.0',
        ]);

        if (null === $data) {
            return Nip05VerificationResult::failure(
                $identifier,
                $expectedPubkey,
                'Failed to fetch or parse .well-known response'
            );
        }

        if (!isset($data['names'])) {
            return Nip05VerificationResult::failure(
                $identifier,
                $expectedPubkey,
                'Response missing names object'
            );
        }

        $localPart = $identifier->getLocalPart();
        if (!isset($data['names'][$localPart])) {
            return Nip05VerificationResult::failure(
                $identifier,
                $expectedPubkey,
                "Name '{$localPart}' not found in response"
            );
        }

        $returnedPubkey = $data['names'][$localPart];
        $expectedHex = $expectedPubkey->toHex();

        if ($returnedPubkey !== $expectedHex) {
            return Nip05VerificationResult::failure(
                $identifier,
                $expectedPubkey,
                'Pubkey mismatch'
            );
        }

        $this->logger->info('NIP-05 verification successful', [
            'identifier' => (string) $identifier,
            'pubkey' => $expectedHex,
        ]);

        return Nip05VerificationResult::success($identifier, $expectedPubkey);
    }
}
