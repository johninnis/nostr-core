<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Adapter;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Port\Nip11ServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayHttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Psr\Log\LoggerInterface;

final class Nip11Adapter implements Nip11ServiceInterface
{
    public function __construct(
        private readonly HttpServiceInterface $httpService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchNip11Info(RelayUrl $relayUrl): ?Nip11Info
    {
        $httpUrl = new RelayHttpUrl($relayUrl);

        $this->logger->debug('Fetching NIP-11 info for relay', [
            'relay_url' => (string) $relayUrl,
            'http_url' => (string) $httpUrl,
        ]);

        $data = $this->httpService->getJson((string) $httpUrl, [
            'Accept' => 'application/nostr+json',
            'User-Agent' => 'Nostr-PHP/1.0',
        ]);

        if (null === $data) {
            return null;
        }

        $this->logger->info('Successfully fetched NIP-11 info', [
            'relay_url' => (string) $relayUrl,
            'fields_count' => count($data),
            'has_name' => isset($data['name']),
            'has_description' => isset($data['description']),
            'has_supported_nips' => isset($data['supported_nips']),
        ]);

        return Nip11Info::fromArray($relayUrl, $data);
    }
}
