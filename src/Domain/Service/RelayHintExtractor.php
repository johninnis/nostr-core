<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final class RelayHintExtractor implements RelayHintExtractorInterface
{
    public function __construct(
        private Nip19CodecInterface $nip19Codec,
    ) {
    }

    #[Override]
    public function extractRelayHints(Event $event): array
    {
        $relays = [];

        $relays = array_merge($relays, $this->extractRelayHintsFromTags($event->getTags()));

        $relays = array_merge($relays, $this->extractRelayHintsFromContent((string) $event->getContent()));

        return $this->deduplicateRelayUrls($relays);
    }

    #[Override]
    public function extractRelayHintsFromTags(TagCollection $tags): array
    {
        $relays = [];

        foreach ($tags->toArray() as $tagArray) {
            if (!is_array($tagArray) || count($tagArray) < 2) {
                continue;
            }

            if ('r' === $tagArray[0] && is_string($tagArray[1]) && $this->isValidUrl($tagArray[1])) {
                $relayUrl = RelayUrl::fromString($tagArray[1]);
                if (null !== $relayUrl) {
                    $relays[] = $relayUrl;
                }
            }

            if (in_array($tagArray[0], [TagType::EVENT, TagType::PUBKEY], true) && count($tagArray) >= 3) {
                if (is_string($tagArray[2]) && $this->isValidUrl($tagArray[2])) {
                    $relayUrl = RelayUrl::fromString($tagArray[2]);
                    if (null !== $relayUrl) {
                        $relays[] = $relayUrl;
                    }
                }
            }
        }

        return $this->deduplicateRelayUrls($relays);
    }

    #[Override]
    public function extractRelayHintsFromContent(string $content): array
    {
        $relays = [];

        if (preg_match_all('/nevent1[a-z0-9]+/', $content, $matches)) {
            foreach ($matches[0] as $nevent) {
                $relay = $this->extractRelayHintFromNevent($nevent);
                if (null !== $relay) {
                    $relays[] = $relay;
                }
            }
        }

        return $relays;
    }

    #[Override]
    public function extractRelayHintFromNevent(string $nevent): ?RelayUrl
    {
        $decoded = $this->nip19Codec->decodeComplexEntity($nevent);
        if (null === $decoded || empty($decoded['relays'])) {
            return null;
        }

        return RelayUrl::fromString($decoded['relays'][0]);
    }

    private function isValidUrl(string $url): bool
    {
        return false !== filter_var($url, FILTER_VALIDATE_URL);
    }

    private function deduplicateRelayUrls(array $relayUrls): array
    {
        $unique = [];

        foreach ($relayUrls as $relayUrl) {
            $unique[(string) $relayUrl] ??= $relayUrl;
        }

        return array_values($unique);
    }
}
