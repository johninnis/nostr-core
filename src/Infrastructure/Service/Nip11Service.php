<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Domain\Service\Nip11ServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Psr\Log\LoggerInterface;

final class Nip11Service implements Nip11ServiceInterface
{
    public function __construct(
        private readonly HttpServiceInterface $httpService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchNip11Info(RelayUrl $relayUrl): ?Nip11Info
    {
        $httpUrl = $this->convertWebSocketToHttp((string) $relayUrl);

        $this->logger->debug('Fetching NIP-11 info for relay', [
            'relay_url' => (string) $relayUrl,
            'http_url' => $httpUrl,
        ]);

        $data = $this->httpService->getJson($httpUrl, [
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

    private function convertWebSocketToHttp(string $wsUrl): string
    {
        if (str_starts_with($wsUrl, 'wss://')) {
            return 'https://'.substr($wsUrl, 6);
        }

        if (str_starts_with($wsUrl, 'ws://')) {
            return 'http://'.substr($wsUrl, 5);
        }

        if (str_starts_with($wsUrl, 'http://') || str_starts_with($wsUrl, 'https://')) {
            return $wsUrl;
        }

        return 'https://'.$wsUrl;
    }
}
