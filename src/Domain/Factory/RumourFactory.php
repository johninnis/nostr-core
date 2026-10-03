<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Factory;

use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\EventKindCategory;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\Service\ContentReferenceTagBuilder;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileEventMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Content\LongformMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;

final readonly class RumourFactory
{
    private const array ROOT_SCOPE_TAG_NAMES = [
        TagType::ROOT_ADDRESS,
        TagType::ROOT_EVENT,
        TagType::ROOT_EXTERNAL_CONTENT,
        TagType::ROOT_KIND,
        TagType::ROOT_PUBKEY,
    ];

    public function __construct(private PublicKey $author)
    {
    }

    public function createFileMetadata(FileEventMetadata $metadata, ?EventContent $caption = null): Rumour
    {
        return Rumour::draft($this->author, EventKind::fromInt(EventKind::FILE_METADATA), $caption, $metadata->toTags());
    }

    public function createRepost(Event $target, RelayUrl $relay): Rumour
    {
        $isShortNote = $target->getKind()->is(EventKind::TEXT_NOTE);
        $coordinate = EventCoordinate::tryFromEvent($target);

        $tags = [
            Tag::event($target->getId(), $relay),
            Tag::pubkey($target->getPubkey()),
        ];

        if (!$isShortNote) {
            $tags[] = Tag::fromArray([TagType::PARENT_KIND, (string) $target->getKind()->toInt()]);
        }

        if (null !== $coordinate) {
            $tags[] = $coordinate->withRelayHint($relay)->toATag();
        }

        return Rumour::draft(
            $this->author,
            EventKind::fromInt($isShortNote ? EventKind::REPOST : EventKind::GENERIC_REPOST),
            $target->isProtected() ? EventContent::empty() : EventContent::fromString($target->toJson()),
            new TagCollection($tags),
        );
    }

    public function createTextNote(EventContent $content): Rumour
    {
        return Rumour::draft($this->author, EventKind::fromInt(EventKind::TEXT_NOTE), $content, ContentReferenceTagBuilder::buildTags($content));
    }

    public function createReply(Event $parent, EventContent $content, ?RelayUrl $hint = null): Rumour
    {
        return $parent->getKind()->is(EventKind::TEXT_NOTE)
            ? Rumour::draft($this->author, EventKind::fromInt(EventKind::TEXT_NOTE), $content, ContentReferenceTagBuilder::buildTags($content, self::shortNoteReplyTags($parent, $hint)))
            : Rumour::draft($this->author, EventKind::fromInt(EventKind::COMMENT), $content, ContentReferenceTagBuilder::buildTags($content, self::commentTags($parent, $hint)));
    }

    public function createReaction(Event $target, ?EventContent $reaction = null, ?RelayUrl $relay = null): Rumour
    {
        $targetAuthor = $target->getPubkey()->toHex();
        $hint = null === $relay ? '' : (string) $relay;
        $tags = [
            Tag::event($target->getId(), $relay, $target->getPubkey()),
            Tag::pubkey($target->getPubkey(), $relay),
            Tag::fromArray([TagType::PARENT_KIND, (string) $target->getKind()->toInt()]),
        ];

        $coordinate = self::addressableCoordinate($target);
        if (null !== $coordinate) {
            $tags[] = Tag::fromArray([TagType::ADDRESSABLE, (string) $coordinate, $hint, $targetAuthor]);
        }

        return Rumour::draft(
            $this->author,
            EventKind::fromInt(EventKind::REACTION),
            $reaction ?? EventContent::fromString('+'),
            new TagCollection($tags),
        );
    }

    public function createAuth(RelayChallenge $relayChallenge): Rumour
    {
        $tags = new TagCollection([
            Tag::fromArray([TagType::RELAY, (string) $relayChallenge->getRelayUrl()]),
            Tag::fromArray([TagType::CHALLENGE, (string) $relayChallenge->getChallenge()]),
        ]);

        return Rumour::draft($this->author, EventKind::fromInt(EventKind::CLIENT_AUTH), tags: $tags);
    }

    public function createHttpAuth(Nip98Request $request): Rumour
    {
        $tags = [
            Tag::fromArray([TagType::URL, (string) $request->getUrl()]),
            Tag::fromArray([TagType::METHOD, $request->getMethod()]),
        ];

        $bodyHash = $request->getBodyHash();
        if (null !== $bodyHash) {
            $tags[] = Tag::fromArray([TagType::PAYLOAD, (string) $bodyHash]);
        }

        return Rumour::draft($this->author, EventKind::fromInt(EventKind::HTTP_AUTH), tags: new TagCollection($tags));
    }

    public function createLongformContent(EventContent $content, LongformMetadata $metadata): Rumour
    {
        return Rumour::draft($this->author, EventKind::fromInt(EventKind::LONGFORM_CONTENT), $content, $metadata->toTags());
    }

    public function createDeletion(Event $target): Rumour
    {
        if (!$target->getPubkey()->equals($this->author)) {
            throw new InvalidArgumentException('A NIP-09 deletion request can only name an event by its own author');
        }

        if ($target->isDeletion()) {
            throw new InvalidArgumentException('A NIP-09 deletion request against a deletion request has no effect');
        }

        $coordinate = EventCoordinate::tryFromEvent($target);

        $tags = new TagCollection([
            null === $coordinate ? Tag::event($target->getId()) : $coordinate->toATag(),
            Tag::fromArray([TagType::PARENT_KIND, (string) $target->getKind()->toInt()]),
        ]);

        return Rumour::draft($this->author, EventKind::fromInt(EventKind::EVENT_DELETION), tags: $tags);
    }

    public function createPrivateMessage(PublicKeyCollection $receivers, EventContent $content, ?Rumour $replyTo = null): Rumour
    {
        $tags = $this->roomTags($receivers);

        if (null !== $replyTo) {
            $tags[] = Tag::event($replyTo->getId());
        }

        return Rumour::draft($this->author, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), $content, new TagCollection($tags));
    }

    // Deliberate: the room is named by its receivers, not read from the message, and the message's author is p-tagged last even when it is the sender — see ADR-0096
    public function createPrivateReaction(PublicKeyCollection $receivers, Rumour $message, ?EventContent $reaction = null): Rumour
    {
        $messageAuthor = $message->getPubkey();

        if (!$messageAuthor->equals($this->author) && !$receivers->contains($messageAuthor)) {
            throw new InvalidArgumentException('A private reaction answers a message whose author is not a member of its room');
        }

        $otherReceivers = $this->roomReceivers($receivers)->diff(new PublicKeyCollection([$messageAuthor]));
        $tags = [
            Tag::event($message->getId(), null, $messageAuthor),
            ...array_map(Tag::pubkey(...), [...$otherReceivers->toArray(), $messageAuthor]),
            Tag::fromArray([TagType::PARENT_KIND, (string) $message->getKind()->toInt()]),
        ];

        return Rumour::draft(
            $this->author,
            EventKind::fromInt(EventKind::REACTION),
            $reaction ?? EventContent::fromString('+'),
            new TagCollection($tags),
        );
    }

    /**
     * @return list<Tag>
     */
    private function roomTags(PublicKeyCollection $receivers): array
    {
        return array_map(Tag::pubkey(...), $this->roomReceivers($receivers)->toArray());
    }

    // Deliberate: a room with no member besides its author is a room of one, named by a p tag for the author, since NIP-17's room is the pubkey and the p tags together — see ADR-0096
    private function roomReceivers(PublicKeyCollection $receivers): PublicKeyCollection
    {
        $others = $receivers->diff(new PublicKeyCollection([$this->author]))->unique();

        return $others->isEmpty() ? new PublicKeyCollection([$this->author]) : $others;
    }

    private static function addressableCoordinate(Event $event): ?EventCoordinate
    {
        return EventKindCategory::Replaceable === $event->getKind()->category() ? null : EventCoordinate::tryFromEvent($event);
    }

    private static function shortNoteReplyTags(Event $parent, ?RelayUrl $hint): TagCollection
    {
        $rootReference = self::inheritedRoot($parent);
        $parentMarker = null === $rootReference ? Nip10Marker::Root : Nip10Marker::Reply;
        $rootAuthor = $rootReference?->getAuthor();

        $participants = new PublicKeyCollection([
            $parent->getPubkey(),
            ...$parent->getTags()->getPubkeys(),
            ...(null === $rootAuthor ? [] : [$rootAuthor]),
        ])->unique();

        return new TagCollection([
            ...(null === $rootReference ? [] : [$rootReference->toETag()]),
            new EventReference($parent->getId(), $hint, $parentMarker, $parent->getPubkey())->toETag(),
            ...array_map(static fn (PublicKey $pubkey): Tag => Tag::pubkey($pubkey), $participants->toArray()),
        ]);
    }

    private static function inheritedRoot(Event $parent): ?EventReference
    {
        $chain = ReplyChainAnalyser::analyse($parent->getTags(), $parent->getKind());
        $root = $chain->getRoot() ?? $chain->getParent();

        if (!$root instanceof EventReference || $root->getEventId()->equals($parent->getId())) {
            return null;
        }

        $author = null === $root->getMarker() ? null : $root->getAuthor();

        return new EventReference($root->getEventId(), $root->getRelayUrl(), Nip10Marker::Root, $author);
    }

    private static function commentTags(Event $parent, ?RelayUrl $hint): TagCollection
    {
        return new TagCollection([...self::commentRootScope($parent, $hint), ...self::commentParentItem($parent, $hint)]);
    }

    /**
     * @return list<Tag>
     */
    private static function commentRootScope(Event $parent, ?RelayUrl $hint): array
    {
        $root = $parent->getKind()->is(EventKind::COMMENT) ? self::scopedRoot($parent) : null;

        return null === $root ? self::rootScopeOf($parent, $hint) : self::copiedRootScope($parent, $root);
    }

    // Deliberate: a parent comment that resolves no root, or no single non-empty K, has no root scope to copy and is itself the root, since NIP-22 comments MUST point to the root scope — see ADR-0085
    private static function scopedRoot(Event $comment): EventReference|EventCoordinate|ExternalContentId|null
    {
        $rootKind = $comment->getTags()->getSoleValueByType(TagType::rootKind())->getValue();

        return null === $rootKind || '' === $rootKind ? null : ReplyChainAnalyser::analyse($comment->getTags(), $comment->getKind())->getRoot();
    }

    /**
     * @return list<Tag>
     */
    // Deliberate: NIP-22 comments MUST point to the authors when one is available, so a copied scope without a P gains one for the root author it names — see ADR-0085
    private static function copiedRootScope(Event $parent, EventReference|EventCoordinate|ExternalContentId $root): array
    {
        $scope = array_map(
            self::copiedScopeTag(...),
            array_values(array_filter(
                $parent->getTags()->toArray(),
                static fn (Tag $tag): bool => in_array((string) $tag->getType(), self::ROOT_SCOPE_TAG_NAMES, true),
            )),
        );
        $namesAuthor = array_any(
            $scope,
            static fn (Tag $tag): bool => $tag->getType()->is(TagType::ROOT_PUBKEY) && null !== PublicKey::tryFromHex($tag->getValue(0) ?? ''),
        );
        $author = $namesAuthor ? null : self::knownRootAuthor($parent, $root);

        return null === $author ? $scope : [...$scope, Tag::fromArray([TagType::ROOT_PUBKEY, $author->toHex()])];
    }

    // Deliberate: a copied external content hint is written as the web URL it reads as, or not at all — see ADR-0085
    private static function copiedScopeTag(Tag $tag): Tag
    {
        if (!$tag->getType()->is(TagType::ROOT_EXTERNAL_CONTENT)) {
            return $tag;
        }

        $page = HttpUrl::tryFromString($tag->getValue(1));

        return Tag::fromArray([TagType::ROOT_EXTERNAL_CONTENT, $tag->getValue(0) ?? '', ...(null === $page ? [] : [(string) $page])]);
    }

    private static function knownRootAuthor(Event $comment, EventReference|EventCoordinate|ExternalContentId $root): ?PublicKey
    {
        return match (true) {
            $root instanceof EventCoordinate => $root->getPubkey(),
            $root instanceof EventReference => self::rootEventAuthor($comment, $root),
            default => null,
        };
    }

    private static function rootEventAuthor(Event $comment, EventReference $root): ?PublicKey
    {
        $authors = array_values(array_filter(array_map(
            static function (Tag $tag) use ($root): ?string {
                $reference = EventReference::tryFromTag($tag);

                return true === $reference?->getEventId()->equals($root->getEventId()) ? $reference->getAuthor()?->toHex() : null;
            },
            $comment->getTags()->findByType(TagType::rootEvent()),
        )));
        $soleAuthor = SoleTagValue::fromValues($authors)->getValue();

        return null === $soleAuthor ? null : PublicKey::tryFromHex($soleAuthor);
    }

    /**
     * @return list<Tag>
     */
    private static function rootScopeOf(Event $event, ?RelayUrl $relayHint): array
    {
        $coordinate = EventCoordinate::tryFromEvent($event);
        $relay = null === $relayHint ? '' : (string) $relayHint;

        return [
            null === $coordinate
                ? Tag::rootEvent($event->getId(), $relayHint, $event->getPubkey())
                : Tag::fromArray([TagType::ROOT_ADDRESS, (string) $coordinate, $relay]),
            Tag::fromArray([TagType::ROOT_KIND, (string) $event->getKind()->toInt()]),
            Tag::fromArray([TagType::ROOT_PUBKEY, $event->getPubkey()->toHex(), ...(null === $relayHint ? [] : [$relay])]),
        ];
    }

    /**
     * @return list<Tag>
     */
    private static function commentParentItem(Event $parent, ?RelayUrl $hint): array
    {
        $relay = null === $hint ? '' : (string) $hint;
        $coordinate = EventCoordinate::tryFromEvent($parent);

        return [
            ...(null === $coordinate ? [] : [Tag::fromArray([TagType::ADDRESSABLE, (string) $coordinate, $relay])]),
            Tag::event($parent->getId(), $hint, $parent->getPubkey()),
            Tag::fromArray([TagType::PARENT_KIND, (string) $parent->getKind()->toInt()]),
            Tag::pubkey($parent->getPubkey(), $hint),
        ];
    }
}
