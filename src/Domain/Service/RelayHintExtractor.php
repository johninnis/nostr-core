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
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class RelayHintExtractor
{
    private function __construct()
    {
    }

    public static function extract(Event $event): RelayUrlCollection
    {
        $relays = [
            ...self::extractFromTags($event->getTags()),
            ...self::extractFromRootScope($event->getTags()),
            ...self::extractFromContent($event->getContent()),
        ];

        return new RelayUrlCollection($relays)->unique();
    }

    /**
     * @return list<RelayUrl>
     */
    private static function extractFromTags(TagCollection $tags): array
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
    // Deliberate: a NIP-22 comment names its root scope in upper-case E, A and P tags, whose relay hints are as much hints as their lower-case parents' — see ADR-0085
    private static function extractFromRootScope(TagCollection $tags): array
    {
        $relayUrls = array_map(static fn (Tag $tag): ?RelayUrl => match ((string) $tag->getType()) {
            TagType::ROOT_EVENT => EventReference::tryFromTag($tag)?->getRelayUrl(),
            TagType::ROOT_ADDRESS => EventCoordinate::tryFromTag($tag)?->getRelayHint(),
            TagType::ROOT_PUBKEY => PubkeyReference::tryFromTag($tag)?->getRelayUrl(),
            default => null,
        }, $tags->toArray());

        return array_values(array_filter($relayUrls, static fn (?RelayUrl $relayUrl): bool => null !== $relayUrl));
    }

    /**
     * @return list<RelayUrl>
     */
    private static function extractFromContent(EventContent $content): array
    {
        $relays = [];

        foreach (ContentReferenceExtractor::extract($content) as $reference) {
            foreach ($reference->getRelays() as $relay) {
                $relays[] = $relay;
            }
        }

        return $relays;
    }
}
