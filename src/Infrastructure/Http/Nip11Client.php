<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Port\Nip11ServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayHttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Override;

final class Nip11Client implements Nip11ServiceInterface
{
    public function __construct(
        private readonly HttpServiceInterface $httpService,
    ) {
    }

    #[Override]
    public function fetchNip11Info(RelayUrl $relayUrl): ?Nip11Info
    {
        $httpUrl = new RelayHttpUrl($relayUrl);

        $data = $this->httpService->getJson((string) $httpUrl, [
            'Accept' => 'application/nostr+json',
            'User-Agent' => 'Nostr-PHP/1.0',
        ]);

        if (null === $data) {
            return null;
        }

        return Nip11Info::fromArray($relayUrl, $data);
    }
}
