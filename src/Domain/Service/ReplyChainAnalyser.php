<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\EventReference;
use Innis\Nostr\Core\Domain\Entity\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class ReplyChainAnalyser
{
    public static function analyse(TagCollection $tags, ?EventKind $kind = null): ReplyChain
    {
        $tagArrays = $tags->toArray();

        if (null !== $kind && EventKind::COMMENT === $kind->toInt()) {
            return self::analyseCommentReplyChain($tagArrays);
        }

        return self::analyseNip10ReplyChain($tagArrays);
    }

    private static function analyseCommentReplyChain(array $tagArrays): ReplyChain
    {
        $rootEvent = null;
        $parentEvent = null;
        $conversationParticipants = [];

        foreach ($tagArrays as $tagArray) {
            if (empty($tagArray) || !is_array($tagArray)) {
                continue;
            }

            if (TagType::ROOT_EVENT === $tagArray[0] && isset($tagArray[1])) {
                $eventId = EventId::fromHex($tagArray[1]);
                if (null !== $eventId) {
                    $author = $tagArray[3] ?? null;
                    $rootEvent = new EventReference(
                        $eventId,
                        RelayUrl::fromString($tagArray[2] ?? null),
                        null,
                        (null !== $author && '' !== $author) ? PublicKey::fromHex($author) : null
                    );
                }
            } elseif (TagType::EVENT === $tagArray[0] && isset($tagArray[1])) {
                $eventId = EventId::fromHex($tagArray[1]);
                if (null !== $eventId) {
                    $author = $tagArray[3] ?? null;
                    $parentEvent = new EventReference(
                        $eventId,
                        RelayUrl::fromString($tagArray[2] ?? null),
                        null,
                        (null !== $author && '' !== $author) ? PublicKey::fromHex($author) : null
                    );
                }
            } elseif (TagType::PUBKEY === $tagArray[0] && isset($tagArray[1])) {
                $pubkey = PublicKey::fromHex($tagArray[1]);
                if (null !== $pubkey) {
                    $conversationParticipants[] = $pubkey;
                }
            }
        }

        $isReply = null !== $parentEvent;

        return new ReplyChain(
            $isReply,
            !$isReply && null === $rootEvent,
            $rootEvent,
            $parentEvent,
            $conversationParticipants,
            []
        );
    }

    private static function analyseNip10ReplyChain(array $tagArrays): ReplyChain
    {
        $isReply = false;
        $isRootPost = true;
        $rootEvent = null;
        $parentEvent = null;
        $conversationParticipants = [];
        $mentionedEvents = [];

        $eTags = [];

        foreach ($tagArrays as $tagArray) {
            if (empty($tagArray) || !is_array($tagArray)) {
                continue;
            }

            if (TagType::EVENT === $tagArray[0] && isset($tagArray[1])) {
                $author = $tagArray[4] ?? null;
                $eTags[] = [
                    'id' => $tagArray[1],
                    'relay' => $tagArray[2] ?? null,
                    'marker' => $tagArray[3] ?? null,
                    'author' => (null !== $author && '' !== $author) ? $author : null,
                ];
            } elseif (TagType::PUBKEY === $tagArray[0] && isset($tagArray[1])) {
                $pubkey = PublicKey::fromHex($tagArray[1]);
                if (null !== $pubkey) {
                    $conversationParticipants[] = $pubkey;
                }
            }
        }

        if (!empty($eTags)) {
            $isReply = true;
            $isRootPost = false;

            $hasMarkers = false;
            foreach ($eTags as $eTag) {
                if (in_array($eTag['marker'], ['root', 'reply', 'mention'], true)) {
                    $hasMarkers = true;
                    break;
                }
            }

            if ($hasMarkers) {
                foreach ($eTags as $eTag) {
                    $eventRef = self::eventReferenceFromETag($eTag);
                    if (null === $eventRef) {
                        continue;
                    }

                    if ('root' === $eTag['marker']) {
                        $rootEvent = $eventRef;
                    } elseif ('reply' === $eTag['marker']) {
                        $parentEvent = $eventRef;
                    } else {
                        $mentionedEvents[] = $eventRef;
                    }
                }
            } else {
                if (1 === count($eTags)) {
                    $parentEvent = self::eventReferenceFromETag($eTags[0]);
                } else {
                    $rootEvent = self::eventReferenceFromETag($eTags[0]);
                    $parentEvent = self::eventReferenceFromETag($eTags[count($eTags) - 1]);

                    for ($i = 1; $i < count($eTags) - 1; ++$i) {
                        $eventRef = self::eventReferenceFromETag($eTags[$i]);
                        if (null !== $eventRef) {
                            $mentionedEvents[] = $eventRef;
                        }
                    }
                }
            }
        }

        return new ReplyChain(
            $isReply,
            $isRootPost,
            $rootEvent,
            $parentEvent,
            $conversationParticipants,
            $mentionedEvents
        );
    }

    private static function eventReferenceFromETag(array $eTag): ?EventReference
    {
        $eventId = EventId::fromHex($eTag['id']);
        if (null === $eventId) {
            return null;
        }

        return new EventReference(
            $eventId,
            RelayUrl::fromString($eTag['relay']),
            $eTag['marker'],
            null !== $eTag['author'] ? PublicKey::fromHex($eTag['author']) : null
        );
    }
}
