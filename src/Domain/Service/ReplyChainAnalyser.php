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

        if ($kind !== null && $kind->toInt() === EventKind::COMMENT) {
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
            if (empty($tagArray) || !\is_array($tagArray)) {
                continue;
            }

            if ($tagArray[0] === TagType::ROOT_EVENT && isset($tagArray[1])) {
                $eventId = EventId::fromHex($tagArray[1]);
                if ($eventId !== null) {
                    $author = $tagArray[3] ?? null;
                    $rootEvent = new EventReference(
                        $eventId,
                        RelayUrl::fromString($tagArray[2] ?? null),
                        null,
                        ($author !== null && $author !== '') ? PublicKey::fromHex($author) : null
                    );
                }
            } elseif ($tagArray[0] === TagType::EVENT && isset($tagArray[1])) {
                $eventId = EventId::fromHex($tagArray[1]);
                if ($eventId !== null) {
                    $author = $tagArray[3] ?? null;
                    $parentEvent = new EventReference(
                        $eventId,
                        RelayUrl::fromString($tagArray[2] ?? null),
                        null,
                        ($author !== null && $author !== '') ? PublicKey::fromHex($author) : null
                    );
                }
            } elseif ($tagArray[0] === TagType::PUBKEY && isset($tagArray[1])) {
                $pubkey = PublicKey::fromHex($tagArray[1]);
                if ($pubkey !== null) {
                    $conversationParticipants[] = $pubkey;
                }
            }
        }

        $isReply = $parentEvent !== null;

        return new ReplyChain(
            $isReply,
            !$isReply && $rootEvent === null,
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
            if (empty($tagArray) || !\is_array($tagArray)) {
                continue;
            }

            if ($tagArray[0] === TagType::EVENT && isset($tagArray[1])) {
                $author = $tagArray[4] ?? null;
                $eTags[] = [
                    'id' => $tagArray[1],
                    'relay' => $tagArray[2] ?? null,
                    'marker' => $tagArray[3] ?? null,
                    'author' => ($author !== null && $author !== '') ? $author : null,
                ];
            } elseif ($tagArray[0] === TagType::PUBKEY && isset($tagArray[1])) {
                $pubkey = PublicKey::fromHex($tagArray[1]);
                if ($pubkey !== null) {
                    $conversationParticipants[] = $pubkey;
                }
            }
        }

        if (!empty($eTags)) {
            $isReply = true;
            $isRootPost = false;

            $hasMarkers = false;
            foreach ($eTags as $eTag) {
                if (\in_array($eTag['marker'], ['root', 'reply', 'mention'], true)) {
                    $hasMarkers = true;
                    break;
                }
            }

            if ($hasMarkers) {
                foreach ($eTags as $eTag) {
                    $eventRef = self::eventReferenceFromETag($eTag);
                    if ($eventRef === null) {
                        continue;
                    }

                    if ($eTag['marker'] === 'root') {
                        $rootEvent = $eventRef;
                    } elseif ($eTag['marker'] === 'reply') {
                        $parentEvent = $eventRef;
                    } else {
                        $mentionedEvents[] = $eventRef;
                    }
                }
            } else {
                if (\count($eTags) === 1) {
                    $parentEvent = self::eventReferenceFromETag($eTags[0]);
                } else {
                    $rootEvent = self::eventReferenceFromETag($eTags[0]);
                    $parentEvent = self::eventReferenceFromETag($eTags[\count($eTags) - 1]);

                    for ($i = 1; $i < \count($eTags) - 1; $i++) {
                        $eventRef = self::eventReferenceFromETag($eTags[$i]);
                        if ($eventRef !== null) {
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
        if ($eventId === null) {
            return null;
        }

        return new EventReference(
            $eventId,
            RelayUrl::fromString($eTag['relay']),
            $eTag['marker'],
            $eTag['author'] !== null ? PublicKey::fromHex($eTag['author']) : null
        );
    }
}
