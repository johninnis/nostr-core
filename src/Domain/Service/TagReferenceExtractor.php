<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class TagReferenceExtractor
{
    private function __construct()
    {
    }

    public static function extract(TagCollection $tags): TagReferences
    {
        $events = [];
        $pubkeys = [];
        $quotes = [];
        $addressable = [];
        $relays = [];
        $challenges = [];

        foreach ($tags as $tag) {
            $value = $tag->getValue(0);

            match ((string) $tag->getType()) {
                TagType::EVENT => self::appendEvent($tag, $value, $events),
                TagType::PUBKEY => self::appendPubkey($tag, $value, $pubkeys),
                TagType::QUOTE => self::appendQuote($tag, $value, $addressable, $quotes),
                TagType::ADDRESSABLE => self::appendAddressable($tag, $value, $addressable),
                TagType::REFERENCE => self::appendRelay($value, $tag, $relays),
                TagType::CHALLENGE => self::appendChallenge($value, $challenges),
                default => null,
            };
        }

        return new TagReferences(
            new EventReferenceCollection($events),
            new PubkeyReferenceCollection($pubkeys),
            new EventReferenceCollection($quotes),
            new EventCoordinateCollection($addressable),
            new RelayReferenceCollection($relays),
            $challenges
        );
    }

    /**
     * @param list<EventReference> $events
     */
    private static function appendEvent(Tag $tag, ?string $value, array &$events): void
    {
        $eventId = null !== $value ? EventId::fromHex($value) : null;
        if (null !== $eventId) {
            $author = $tag->getValue(3);
            $events[] = new EventReference(
                $eventId,
                RelayUrl::fromString($tag->getValue(1)),
                $tag->getValue(2),
                null !== $author ? PublicKey::fromHex($author) : null
            );
        }
    }

    /**
     * @param list<PubkeyReference> $pubkeys
     */
    private static function appendPubkey(Tag $tag, ?string $value, array &$pubkeys): void
    {
        $pubkey = null !== $value ? PublicKey::fromHex($value) : null;
        if (null !== $pubkey) {
            $pubkeys[] = new PubkeyReference(
                $pubkey,
                RelayUrl::fromString($tag->getValue(1)),
                $tag->getValue(2)
            );
        }
    }

    /**
     * @param list<EventCoordinate> $addressable
     * @param list<EventReference>  $quotes
     */
    private static function appendQuote(Tag $tag, ?string $value, array &$addressable, array &$quotes): void
    {
        if (null === $value) {
            return;
        }

        $relayHint = $tag->getValue(1);
        if (str_contains($value, ':')) {
            $coordinate = EventCoordinate::fromString($value, $relayHint);
            if (null !== $coordinate) {
                $addressable[] = $coordinate;
            }

            return;
        }

        $eventId = EventId::fromHex($value);
        if (null !== $eventId) {
            $author = $tag->getValue(2);
            $quotes[] = new EventReference(
                $eventId,
                RelayUrl::fromString($relayHint),
                null,
                null !== $author ? PublicKey::fromHex($author) : null
            );
        }
    }

    /**
     * @param list<EventCoordinate> $addressable
     */
    private static function appendAddressable(Tag $tag, ?string $value, array &$addressable): void
    {
        if (null === $value) {
            return;
        }

        $coordinate = EventCoordinate::fromATag($tag->toArray());
        if (null !== $coordinate) {
            $addressable[] = $coordinate;
        }
    }

    /**
     * @param list<RelayReference> $relays
     */
    private static function appendRelay(?string $value, Tag $tag, array &$relays): void
    {
        if (null === $value) {
            return;
        }

        $relayUrl = RelayUrl::fromString($value);
        if (null !== $relayUrl) {
            $relays[] = new RelayReference($relayUrl, $tag->getValue(1));
        }
    }

    /**
     * @param list<string> $challenges
     */
    private static function appendChallenge(?string $value, array &$challenges): void
    {
        if (null !== $value) {
            $challenges[] = $value;
        }
    }
}
