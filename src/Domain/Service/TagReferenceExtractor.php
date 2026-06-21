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
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class TagReferenceExtractor
{
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

            switch ((string) $tag->getType()) {
                case TagType::EVENT:
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
                    break;

                case TagType::PUBKEY:
                    $pubkey = null !== $value ? PublicKey::fromHex($value) : null;
                    if (null !== $pubkey) {
                        $pubkeys[] = new PubkeyReference(
                            $pubkey,
                            RelayUrl::fromString($tag->getValue(1)),
                            $tag->getValue(2)
                        );
                    }
                    break;

                case 'q':
                    if (null !== $value) {
                        $relayHint = $tag->getValue(1);
                        if (str_contains($value, ':')) {
                            $coordinate = EventCoordinate::fromString($value, $relayHint);
                            if (null !== $coordinate) {
                                $addressable[] = $coordinate;
                            }
                        } else {
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
                    }
                    break;

                case TagType::ADDRESSABLE:
                    if (null !== $value) {
                        $coordinate = EventCoordinate::fromATag($tag->toArray());
                        if (null !== $coordinate) {
                            $addressable[] = $coordinate;
                        }
                    }
                    break;

                case 'r':
                    if (null !== $value) {
                        $relayUrl = RelayUrl::fromString($value);
                        if (null !== $relayUrl) {
                            $relays[] = new RelayReference($relayUrl, $tag->getValue(1));
                        }
                    }
                    break;

                case 'challenge':
                    if (null !== $value) {
                        $challenges[] = $value;
                    }
                    break;
            }
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
}
