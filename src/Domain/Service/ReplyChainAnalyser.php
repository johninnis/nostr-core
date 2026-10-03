<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
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
            return self::analyseCommentReplyChain($tags, $kind);
        }

        return self::analyseNip10ReplyChain($tags, $kind);
    }

    // Deliberate: the root and the parent are each read address first, then event, then external content, every tag name as one claim — see ADR-0085
    private static function analyseCommentReplyChain(TagCollection $tags, EventKind $kind): ReplyChain
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

    // Deliberate: the hint is a sole claim among the web URLs the tags carry, read in canonical form, so the tag order never picks one — see ADR-0085
    private static function soleHint(TagCollection $tags, TagType $idType): ?string
    {
        $urls = array_values(array_filter(array_map(static fn (Tag $tag): ?HttpUrl => HttpUrl::tryFromString($tag->getValue(1)), $tags->findByType($idType))));

        return SoleTagValue::fromValues(array_map(strval(...), $urls))->getValue();
    }

    private static function analyseNip10ReplyChain(TagCollection $tags, ?EventKind $kind): ReplyChain
    {
        $references = TagReferenceExtractor::extract($tags);
        $eventReferences = $references->getEvents()->toArray();
        $participants = new PublicKeyCollection(array_map(
            static fn (PubkeyReference $reference): PublicKey => $reference->getPubkey(),
            $references->getPubkeys()->toArray()
        ))->unique();

        $rootEvent = null;
        $parentEvent = null;
        $mentionedEvents = [];

        $hasMarkers = array_any(
            $eventReferences,
            static fn (EventReference $reference): bool => null !== $reference->getMarker(),
        );

        if ($hasMarkers) {
            $rootEvent = self::soleClaim(self::markedAs($eventReferences, Nip10Marker::Root), self::eventIdKey(...));
            $parentEvent = self::soleClaim(self::markedAs($eventReferences, Nip10Marker::Reply), self::eventIdKey(...));
            $mentionedEvents = array_values(array_filter(
                $eventReferences,
                static fn (EventReference $reference): bool => !in_array($reference->getMarker(), [Nip10Marker::Root, Nip10Marker::Reply], true),
            ));
        } elseif (1 === count($eventReferences)) {
            $parentEvent = $eventReferences[0];
        } elseif (count($eventReferences) > 1) {
            $rootEvent = $eventReferences[0];
            $parentEvent = $eventReferences[count($eventReferences) - 1];
            $mentionedEvents = array_slice($eventReferences, 1, -1);
        }

        return new ReplyChain(
            $kind,
            $rootEvent,
            $parentEvent,
            $participants,
            new EventReferenceCollection($mentionedEvents)
        );
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
