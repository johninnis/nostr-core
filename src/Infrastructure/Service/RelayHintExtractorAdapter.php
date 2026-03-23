<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\Service\RelayHintExtractionServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Psr\Log\LoggerInterface;

final class RelayHintExtractorAdapter implements RelayHintExtractionServiceInterface
{
    public function __construct(
        private Bech32EncoderInterface $bech32Encoder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function extractRelayHints(Event $event): array
    {
        $relays = [];

        $relays = array_merge($relays, $this->extractRelayHintsFromTags($event->getTags()));

        $relays = array_merge($relays, $this->extractRelayHintsFromContent((string) $event->getContent()));

        return $this->deduplicateRelayUrls($relays);
    }

    public function extractRelayHintsFromTags(TagCollection $tags): array
    {
        $relays = [];

        foreach ($tags->toArray() as $tagArray) {
            if (!\is_array($tagArray) || \count($tagArray) < 2) {
                continue;
            }

            if ($tagArray[0] === 'r' && $this->isValidUrl($tagArray[1])) {
                $relayUrl = RelayUrl::fromString($tagArray[1]);
                if ($relayUrl !== null) {
                    $relays[] = $relayUrl;
                }
            }

            if (\in_array($tagArray[0], [TagType::EVENT, TagType::PUBKEY], true) && \count($tagArray) >= 3) {
                if ($this->isValidUrl($tagArray[2])) {
                    $relayUrl = RelayUrl::fromString($tagArray[2]);
                    if ($relayUrl !== null) {
                        $relays[] = $relayUrl;
                    }
                }
            }
        }

        return $this->deduplicateRelayUrls($relays);
    }

    public function extractRelayHintsFromContent(string $content): array
    {
        $relays = [];

        if (preg_match_all('/nevent1[a-z0-9]+/', $content, $matches)) {
            foreach ($matches[0] as $nevent) {
                $relay = $this->extractRelayHintFromNevent($nevent);
                if ($relay !== null) {
                    $relays[] = $relay;
                }
            }
        }

        return $relays;
    }

    public function extractRelayHintFromNevent(string $nevent): ?RelayUrl
    {
        try {
            $decoded = $this->bech32Encoder->decodeComplexEntity($nevent);
            if (isset($decoded['relays']) && !empty($decoded['relays'])) {
                return RelayUrl::fromString($decoded['relays'][0]);
            }
            return null;
        } catch (\Exception $e) {
            $this->logger->debug('Failed to decode nevent for relay hint', ['nevent' => $nevent, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function deduplicateRelayUrls(array $relayUrls): array
    {
        $unique = [];
        $seen = [];

        foreach ($relayUrls as $relayUrl) {
            $urlString = (string) $relayUrl;
            if (!isset($seen[$urlString])) {
                $unique[] = $relayUrl;
                $seen[$urlString] = true;
            }
        }

        return $unique;
    }
}
