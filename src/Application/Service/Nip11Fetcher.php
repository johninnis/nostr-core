<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\Port\HttpFetchFailure;
use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Override;

final readonly class Nip11Fetcher implements Nip11FetcherInterface
{
    public function __construct(
        private HttpServiceInterface $httpService,
    ) {
    }

    #[Override]
    public function fetchNip11Info(RelayUrl $relayUrl): ?Nip11Info
    {
        $body = $this->httpService->get($relayUrl->toHttpUrl(), [
            'Accept' => Nip11Info::MEDIA_TYPE,
            'User-Agent' => HttpServiceInterface::USER_AGENT,
        ]);

        return $body instanceof HttpFetchFailure ? null : Nip11Info::tryFromJson($relayUrl, $body);
    }
}
