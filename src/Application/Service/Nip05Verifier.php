<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\Port\HttpFetchFailure;
use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
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
        $body = $this->httpService->get($identifier->getWellKnownUrl(), [
            'Accept' => 'application/json',
            'User-Agent' => HttpServiceInterface::USER_AGENT,
        ]);

        if ($body instanceof HttpFetchFailure) {
            return match ($body) {
                HttpFetchFailure::NotFound => Nip05VerificationFailure::NameNotFound,
                HttpFetchFailure::NoAnswer => Nip05VerificationFailure::FetchFailed,
            };
        }

        return Nip05DocumentVerifier::verify($body, $identifier, $expectedPubkey);
    }
}
