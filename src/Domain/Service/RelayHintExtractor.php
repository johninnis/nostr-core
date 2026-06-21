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
        return $this->deduplicateRelayUrls([
            ...$this->extractRelayHintsFromTags($event->getTags()),
            ...$this->extractRelayHintsFromContent((string) $event->getContent()),
        ]);
    }

    #[Override]
    public function extractRelayHintsFromTags(TagCollection $tags): array
    {
        $relays = [];

        foreach ($tags as $tag) {
            $type = $tag->getType();
            $relayValue = match (true) {
                $type->is(TagType::REFERENCE) => $tag->getValue(0),
                $type->is(TagType::EVENT), $type->is(TagType::PUBKEY) => $tag->getValue(1),
                default => null,
            };

            $relayUrl = RelayUrl::fromString($relayValue);
            if (null !== $relayUrl) {
                $relays[] = $relayUrl;
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
        return $this->nip19Codec->decodeComplexEntity($nevent)?->getRelays()->toArray()[0] ?? null;
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
