<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
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
        $references = TagReferenceExtractor::extract($tags);

        $relayUrls = [
            ...array_map(static fn (EventReference $event): ?RelayUrl => $event->getRelayUrl(), $references->getEvents()->toArray()),
            ...array_map(static fn (EventReference $quote): ?RelayUrl => $quote->getRelayUrl(), $references->getQuotes()->toArray()),
            ...array_map(static fn (PubkeyReference $pubkey): ?RelayUrl => $pubkey->getRelayUrl(), $references->getPubkeys()->toArray()),
            ...array_map(static fn (EventCoordinate $coordinate): ?RelayUrl => $coordinate->getRelayHint(), $references->getAddressable()->toArray()),
            ...array_map(static fn (RelayReference $relay): RelayUrl => $relay->getRelayUrl(), $references->getRelays()->toArray()),
        ];

        return array_values(array_filter($relayUrls, static fn (?RelayUrl $relayUrl): bool => null !== $relayUrl));
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
