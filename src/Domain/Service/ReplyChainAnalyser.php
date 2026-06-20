<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\EventReference;
use Innis\Nostr\Core\Domain\Entity\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Entity\ReplyChain;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class ReplyChainAnalyser
{
    public static function analyse(TagCollection $tags, ?EventKind $kind = null): ReplyChain
    {
        if (null !== $kind && $kind->equals(EventKind::comment())) {
            return self::analyseCommentReplyChain($tags->toArray());
        }

        return self::analyseNip10ReplyChain($tags);
    }

    private static function analyseCommentReplyChain(array $tagArrays): ReplyChain
    {
        $rootEvent = null;
        $parentEvent = null;
        $conversationParticipants = [];

        foreach ($tagArrays as $tagArray) {
            if (!is_array($tagArray) || !isset($tagArray[1]) || !is_string($tagArray[1])) {
                continue;
            }

            $marker = $tagArray[0] ?? null;
            $relay = is_string($tagArray[2] ?? null) ? $tagArray[2] : null;
            $author = is_string($tagArray[3] ?? null) ? $tagArray[3] : null;

            if (TagType::ROOT_EVENT === $marker) {
                $eventId = EventId::fromHex($tagArray[1]);
                if (null !== $eventId) {
                    $rootEvent = new EventReference(
                        $eventId,
                        RelayUrl::fromString($relay),
                        null,
                        (null !== $author && '' !== $author) ? PublicKey::fromHex($author) : null
                    );
                }
            } elseif (TagType::EVENT === $marker) {
                $eventId = EventId::fromHex($tagArray[1]);
                if (null !== $eventId) {
                    $parentEvent = new EventReference(
                        $eventId,
                        RelayUrl::fromString($relay),
                        null,
                        (null !== $author && '' !== $author) ? PublicKey::fromHex($author) : null
                    );
                }
            } elseif (TagType::PUBKEY === $marker) {
                $pubkey = PublicKey::fromHex($tagArray[1]);
                if (null !== $pubkey) {
                    $conversationParticipants[] = $pubkey;
                }
            }
        }

        $isReply = null !== $parentEvent || null !== $rootEvent;

        return new ReplyChain(
            $isReply,
            !$isReply,
            $rootEvent,
            $parentEvent,
            new PublicKeyCollection($conversationParticipants),
            EventReferenceCollection::empty()
        );
    }

    private static function analyseNip10ReplyChain(TagCollection $tags): ReplyChain
    {
        $references = TagReferenceExtractor::extract($tags);
        $eventReferences = $references->getEvents()->toArray();
        $participants = new PublicKeyCollection(array_map(
            static fn (PubkeyReference $reference): PublicKey => $reference->getPubkey(),
            $references->getPubkeys()->toArray()
        ));

        if ([] === $tags->findByType(TagType::event())) {
            return new ReplyChain(false, true, null, null, $participants, EventReferenceCollection::empty());
        }

        $rootEvent = null;
        $parentEvent = null;
        $mentionedEvents = [];

        $hasMarkers = array_any(
            $eventReferences,
            static fn (EventReference $reference): bool => null !== Nip10Marker::tryFrom($reference->getMarker() ?? ''),
        );

        if ($hasMarkers) {
            foreach ($eventReferences as $reference) {
                $marker = Nip10Marker::tryFrom($reference->getMarker() ?? '');
                if (Nip10Marker::Root === $marker) {
                    $rootEvent = $reference;
                } elseif (Nip10Marker::Reply === $marker) {
                    $parentEvent = $reference;
                } else {
                    $mentionedEvents[] = $reference;
                }
            }
        } elseif (1 === count($eventReferences)) {
            $parentEvent = $eventReferences[0];
        } elseif (count($eventReferences) > 1) {
            $rootEvent = $eventReferences[0];
            $parentEvent = $eventReferences[count($eventReferences) - 1];
            $mentionedEvents = array_slice($eventReferences, 1, -1);
        }

        return new ReplyChain(
            true,
            false,
            $rootEvent,
            $parentEvent,
            $participants,
            new EventReferenceCollection($mentionedEvents)
        );
    }
}
