<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class ReplyChainAnalyser
{
    private function __construct()
    {
    }

    public static function analyse(TagCollection $tags, ?EventKind $kind = null): ReplyChain
    {
        if (null !== $kind && $kind->is(EventKind::COMMENT)) {
            return self::analyseCommentReplyChain($tags);
        }

        return self::analyseNip10ReplyChain($tags);
    }

    private static function analyseCommentReplyChain(TagCollection $tags): ReplyChain
    {
        $rootEvent = null;
        $parentEvent = null;
        $conversationParticipants = [];

        foreach ($tags as $tag) {
            $value = $tag->getValue(0);
            if (null === $value) {
                continue;
            }

            $type = $tag->getType();

            if ($type->is(TagType::ROOT_EVENT)) {
                $rootEvent = self::commentEventReference($value, $tag) ?? $rootEvent;
            } elseif ($type->is(TagType::EVENT)) {
                $parentEvent = self::commentEventReference($value, $tag) ?? $parentEvent;
            } elseif ($type->is(TagType::PUBKEY) || $type->is(TagType::SENDER_PUBKEY)) {
                $pubkey = PublicKey::fromHex($value);
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
            new EventReferenceCollection()
        );
    }

    private static function commentEventReference(string $eventIdHex, Tag $tag): ?EventReference
    {
        $eventId = EventId::fromHex($eventIdHex);
        if (null === $eventId) {
            return null;
        }

        $author = $tag->getValue(2);

        return new EventReference(
            $eventId,
            RelayUrl::fromString($tag->getValue(1)),
            null,
            (null !== $author && '' !== $author) ? PublicKey::fromHex($author) : null,
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
            return new ReplyChain(false, true, null, null, $participants, new EventReferenceCollection());
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
