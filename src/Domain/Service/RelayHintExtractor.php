<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class RelayHintExtractor implements RelayHintExtractorInterface
{
    public function __construct(
        private ContentReferenceExtractorInterface $contentReferenceExtractor,
    ) {
    }

    #[Override]
    public function extractRelayHints(Event $event): RelayUrlCollection
    {
        $relays = [
            ...$this->extractFromTags($event->getTags()),
            ...$this->extractFromContent($event->getContent()),
        ];

        return new RelayUrlCollection($relays)->unique();
    }

    /**
     * @return list<RelayUrl>
     */
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

    /**
     * @return list<RelayUrl>
     */
    private function extractFromContent(EventContent $content): array
    {
        $relays = [];

        foreach ($this->contentReferenceExtractor->extractContentReferences($content) as $reference) {
            foreach ($reference->getRelays() as $relay) {
                $relays[] = $relay;
            }
        }

        return $relays;
    }
}
