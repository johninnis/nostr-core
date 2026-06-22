<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrlCollection;
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
    public function extractRelayHints(Event $event): RelayUrlCollection
    {
        $relays = [
            ...$this->extractFromTags($event->getTags()),
            ...$this->extractFromContent((string) $event->getContent()),
        ];

        return new RelayUrlCollection($relays)->unique();
    }

    private function extractFromTags(TagCollection $tags): array
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

        return $relays;
    }

    private function extractFromContent(string $content): array
    {
        $relays = [];

        if (preg_match_all('/nevent1[a-z0-9]+/', $content, $matches)) {
            foreach ($matches[0] as $nevent) {
                $relay = $this->extractFromNevent($nevent);
                if (null !== $relay) {
                    $relays[] = $relay;
                }
            }
        }

        return $relays;
    }

    private function extractFromNevent(string $nevent): ?RelayUrl
    {
        return $this->nip19Codec->decodeComplexEntity($nevent)?->getRelays()->toArray()[0] ?? null;
    }
}
