<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Port\Nip05VerifierInterface;
use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\Service\Nip05DocumentVerifier;
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

        return Nip05DocumentVerifier::verify($data, $identifier, $expectedPubkey);
    }
}
