<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\Enum\ThreadingConvention;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\WebUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class ReplyChainAnalyser
{
    private function __construct()
    {
    }

    public static function analyse(TagCollection $tags, ?EventKind $kind = null): ReplyChain
    {
        return match (ThreadingConvention::ofKind($kind)) {
            ThreadingConvention::Comment => self::analyseCommentReplyChain($tags, $kind),
            // Deliberate: a channel message reads its e tags by marker alone, so its root is the channel, not a message — see nostr-adrs ADR-0012
            ThreadingConvention::Channel => self::markedReplyChain(TagReferenceExtractor::extract($tags), $kind),
            ThreadingConvention::LiveChat => self::analyseLiveChatReplyChain($tags, $kind),
            ThreadingConvention::Chat => self::analyseChatReplyChain($tags, $kind),
            ThreadingConvention::ShortNote, null => self::analyseNip10ReplyChain($tags, $kind),
        };
    }

    // Deliberate: the root and the parent are each read address first, then event, then external content, every tag name as one claim — see ADR-0085
    private static function analyseCommentReplyChain(TagCollection $tags, ?EventKind $kind): ReplyChain
    {
        $rootEvents = [];
        $parentEvents = [];
        $rootAddresses = [];
        $parentAddresses = [];
        $conversationParticipants = [];

        foreach ($tags as $tag) {
            $value = $tag->getValue(0);
            if (null === $value) {
                continue;
            }

            $type = $tag->getType();

            if ($type->is(TagType::ROOT_EVENT)) {
                $rootEvents[] = EventReference::tryFromTag($tag);
            } elseif ($type->is(TagType::EVENT)) {
                $parentEvents[] = EventReference::tryFromTag($tag);
            } elseif ($type->is(TagType::ROOT_ADDRESS)) {
                $rootAddresses[] = EventCoordinate::tryFromTag($tag);
            } elseif ($type->is(TagType::ADDRESSABLE)) {
                $parentAddresses[] = EventCoordinate::tryFromTag($tag);
            } elseif ($type->is(TagType::PUBKEY) || $type->is(TagType::ROOT_PUBKEY)) {
                $pubkey = PublicKey::tryFromHex($value);
                if (null !== $pubkey) {
                    $conversationParticipants[] = $pubkey;
                }
            }
        }

        return new ReplyChain(
            $kind,
            self::soleClaim($rootAddresses, strval(...)) ?? self::soleClaim($rootEvents, self::eventIdKey(...)) ?? self::externalContent($tags, TagType::rootExternalContent(), TagType::rootKind()),
            self::soleClaim($parentAddresses, strval(...)) ?? self::soleClaim($parentEvents, self::eventIdKey(...)) ?? self::externalContent($tags, TagType::externalContent(), TagType::parentKind()),
            new PublicKeyCollection($conversationParticipants)->unique(),
            new EventReferenceCollection(),
        );
    }

    /**
     * @template T of object
     *
     * @param list<T|null>        $claims
     * @param callable(T): string $keyOf
     *
     * @return T|null
     */
    private static function soleClaim(array $claims, callable $keyOf): ?object
    {
        $named = array_values(array_filter($claims));
        $soleKey = SoleTagValue::fromValues(array_map($keyOf, $named))->getValue();

        return null === $soleKey ? null : $named[0];
    }

    private static function eventIdKey(EventReference $reference): string
    {
        return $reference->getEventId()->identityKey();
    }

    // Deliberate: external content is named by one I (or i) value paired with one K (or k) value, its NIP-73 type, each a sole claim — see ADR-0085
    private static function externalContent(TagCollection $tags, TagType $idType, TagType $kindType): ?ExternalContentId
    {
        $value = $tags->getSoleValueByType($idType)->getValue();
        $kind = $tags->getSoleValueByType($kindType)->getValue();
        if (null === $value || null === $kind) {
            return null;
        }

        return ExternalContentId::tryFromString($value, $kind, self::soleHint($tags, $idType));
    }

    // Deliberate: the hint is a sole claim among the web URLs the tags carry, compared exactly as written, so the tag order never picks a spelling — see ADR-0085
    private static function soleHint(TagCollection $tags, TagType $idType): ?string
    {
        return SoleTagValue::fromValues(
            $tags->getValuesByType($idType, 1),
            static fn (string $hint): ?string => WebUrl::tryFromString($hint)?->__toString(),
        )->getValue();
    }

    private static function analyseNip10ReplyChain(TagCollection $tags, ?EventKind $kind): ReplyChain
    {
        $references = TagReferenceExtractor::extract($tags);
        $hasMarkers = array_any(
            $references->getEvents()->toArray(),
            static fn (EventReference $reference): bool => null !== $reference->getMarker(),
        );

        return $hasMarkers ? self::markedReplyChain($references, $kind) : self::positionalReplyChain($references, $kind);
    }

    private static function markedReplyChain(TagReferences $references, ?EventKind $kind): ReplyChain
    {
        $events = $references->getEvents()->toArray();

        return new ReplyChain(
            $kind,
            self::soleClaim(self::markedAs($events, Nip10Marker::Root), self::eventIdKey(...)),
            self::soleClaim(self::markedAs($events, Nip10Marker::Reply), self::eventIdKey(...)),
            self::participants($references),
            new EventReferenceCollection(array_values(array_filter(
                $events,
                static fn (EventReference $reference): bool => !in_array($reference->getMarker(), [Nip10Marker::Root, Nip10Marker::Reply], true),
            ))),
        );
    }

    private static function positionalReplyChain(TagReferences $references, ?EventKind $kind): ReplyChain
    {
        $events = $references->getEvents()->toArray();
        $count = count($events);

        return new ReplyChain(
            $kind,
            $count > 1 ? $events[0] : null,
            $count > 0 ? $events[$count - 1] : null,
            self::participants($references),
            new EventReferenceCollection(array_slice($events, 1, -1)),
        );
    }

    // Deliberate: a live chat message names its parent in its e tags and has no root, its a tag naming the activity — see nostr-adrs ADR-0012
    private static function analyseLiveChatReplyChain(TagCollection $tags, ?EventKind $kind): ReplyChain
    {
        $references = TagReferenceExtractor::extract($tags);

        return self::rootlessReplyChain($references, self::soleClaim($references->getEvents()->toArray(), self::eventIdKey(...)), $kind);
    }

    // Deliberate: a chat message's first q tag naming an event is its parent, the one place q threads, and it has no root — see nostr-adrs ADR-0012
    private static function analyseChatReplyChain(TagCollection $tags, ?EventKind $kind): ReplyChain
    {
        $references = TagReferenceExtractor::extract($tags);

        return self::rootlessReplyChain($references, $references->getQuotes()->toArray()[0] ?? null, $kind);
    }

    private static function rootlessReplyChain(TagReferences $references, ?EventReference $parent, ?EventKind $kind): ReplyChain
    {
        return new ReplyChain($kind, null, $parent, self::participants($references), new EventReferenceCollection());
    }

    private static function participants(TagReferences $references): PublicKeyCollection
    {
        return new PublicKeyCollection(array_map(
            static fn (PubkeyReference $reference): PublicKey => $reference->getPubkey(),
            $references->getPubkeys()->toArray(),
        ))->unique();
    }

    /**
     * @param list<EventReference> $references
     *
     * @return list<EventReference>
     */
    private static function markedAs(array $references, Nip10Marker $marker): array
    {
        return array_values(array_filter($references, static fn (EventReference $reference): bool => $marker === $reference->getMarker()));
    }
}
