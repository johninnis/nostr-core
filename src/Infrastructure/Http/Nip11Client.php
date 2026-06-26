<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Port\Nip11FetcherInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Override;

final readonly class Nip11Client implements Nip11FetcherInterface
{
    public function __construct(
        private HttpServiceInterface $httpService,
    ) {
    }

    #[Override]
    public function fetchNip11Info(RelayUrl $relayUrl): ?Nip11Info
    {
        $data = $this->httpService->getJson($relayUrl->toHttpUrl(), [
            'Accept' => 'application/nostr+json',
            'User-Agent' => UserAgent::DEFAULT,
        ]);

        if (null === $data) {
            return null;
        }

        return Nip11Info::fromArray($relayUrl, $data);
    }
}
