<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\EventReference;
use Innis\Nostr\Core\Domain\Entity\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class TagReferenceExtractor
{
    public static function extract(TagCollection $tags): TagReferences
    {
        $tagArrays = $tags->toArray();
        $events = [];
        $pubkeys = [];
        $quotes = [];
        $addressable = [];
        $relays = [];
        $challenges = [];

        foreach ($tagArrays as $tagArray) {
            if (empty($tagArray) || !\is_array($tagArray)) {
                continue;
            }

            $tagType = $tagArray[0] ?? '';

            switch ($tagType) {
                case TagType::EVENT:
                    $eventId = isset($tagArray[1]) ? EventId::fromHex($tagArray[1]) : null;
                    if ($eventId !== null) {
                        $events[] = new EventReference(
                            $eventId,
                            RelayUrl::fromString($tagArray[2] ?? null),
                            $tagArray[3] ?? null,
                            isset($tagArray[4]) ? PublicKey::fromHex($tagArray[4]) : null
                        );
                    }
                    break;

                case TagType::PUBKEY:
                    $pubkey = isset($tagArray[1]) ? PublicKey::fromHex($tagArray[1]) : null;
                    if ($pubkey !== null) {
                        $pubkeys[] = new PubkeyReference(
                            $pubkey,
                            RelayUrl::fromString($tagArray[2] ?? null),
                            $tagArray[3] ?? null
                        );
                    }
                    break;

                case 'q':
                    if (isset($tagArray[1])) {
                        if (str_contains($tagArray[1], ':')) {
                            $coordinate = EventCoordinate::fromString($tagArray[1], $tagArray[2] ?? null);
                            if ($coordinate !== null) {
                                $addressable[] = $coordinate;
                            }
                        } else {
                            $eventId = EventId::fromHex($tagArray[1]);
                            if ($eventId !== null) {
                                $quotes[] = new EventReference(
                                    $eventId,
                                    RelayUrl::fromString($tagArray[2] ?? null),
                                    null,
                                    isset($tagArray[3]) ? PublicKey::fromHex($tagArray[3]) : null
                                );
                            }
                        }
                    }
                    break;

                case 'a':
                    if (isset($tagArray[1])) {
                        $coordinate = EventCoordinate::fromATag($tagArray);
                        if ($coordinate !== null) {
                            $addressable[] = $coordinate;
                        }
                    }
                    break;

                case 'r':
                    if (isset($tagArray[1]) && \is_string($tagArray[1])) {
                        $relayUrl = RelayUrl::fromString($tagArray[1]);
                        if ($relayUrl !== null) {
                            $relays[] = new RelayReference($relayUrl, $tagArray[2] ?? null);
                        }
                    }
                    break;

                case 'challenge':
                    if (isset($tagArray[1]) && \is_string($tagArray[1])) {
                        $challenges[] = $tagArray[1];
                    }
                    break;
            }
        }

        return new TagReferences($events, $pubkeys, $quotes, $addressable, $relays, $challenges);
    }
}
