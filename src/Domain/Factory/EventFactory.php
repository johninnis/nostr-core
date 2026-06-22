<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Factory;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Content\LongformMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final class EventFactory
{
    public static function createCustomKind(
        PublicKey $pubkey,
        EventKind $kind,
        EventContent $content,
        ?TagCollection $tags = null,
        ?Timestamp $createdAt = null,
    ): Event {
        return new Event(
            $pubkey,
            $createdAt ?? Timestamp::now(),
            $kind,
            $tags ?? TagCollection::empty(),
            $content,
        );
    }

    public static function createTextNote(
        PublicKey $pubkey,
        string $content,
        ?TagCollection $tags = null,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::TEXT_NOTE), EventContent::fromString($content), $tags);
    }

    public static function createMetadata(
        PublicKey $pubkey,
        string $metadata,
        ?TagCollection $tags = null,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::METADATA), EventContent::fromString($metadata), $tags);
    }

    public static function createEncryptedDirectMessage(
        PublicKey $pubkey,
        string $encryptedContent,
        TagCollection $tags,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::ENCRYPTED_DIRECT_MESSAGE), EventContent::fromString($encryptedContent), $tags);
    }

    public static function createEventDeletion(
        PublicKey $pubkey,
        TagCollection $tags,
        string $reason = '',
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::EVENT_DELETION), EventContent::fromString($reason), $tags);
    }

    public static function createFileMetadata(
        PublicKey $pubkey,
        FileMetadata $metadata,
        string $caption = '',
        ?Timestamp $createdAt = null,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::FILE_METADATA), EventContent::fromString($caption), $metadata->toTags(), $createdAt);
    }

    public static function createRepost(PublicKey $pubkey, Event $originalEvent): Event
    {
        $tags = new TagCollection([
            Tag::event($originalEvent->getId()->toHex()),
            Tag::pubkey($originalEvent->getPubkey()->toHex()),
        ]);

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::REPOST), EventContent::fromString(''), $tags);
    }

    public static function createReaction(
        PublicKey $pubkey,
        Event $targetEvent,
        string $reaction = '+',
    ): Event {
        $tags = new TagCollection([
            Tag::event($targetEvent->getId()->toHex()),
            Tag::pubkey($targetEvent->getPubkey()->toHex()),
        ]);

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::REACTION), EventContent::fromString($reaction), $tags);
    }

    public static function createFollowList(PublicKey $pubkey, TagCollection $followTags): Event
    {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::FOLLOW_LIST), EventContent::fromString(''), $followTags);
    }

    public static function createRelayList(PublicKey $pubkey, TagCollection $relayTags): Event
    {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::RELAY_LIST), EventContent::fromString(''), $relayTags);
    }

    public static function createMuteList(PublicKey $pubkey, TagCollection $muteTags): Event
    {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::MUTE_LIST), EventContent::fromString(''), $muteTags);
    }

    public static function createAuth(
        PublicKey $pubkey,
        RelayUrl $relayUrl,
        string $challenge,
    ): Event {
        $tags = new TagCollection([
            Tag::create(TagType::RELAY, (string) $relayUrl),
            Tag::create(TagType::CHALLENGE, $challenge),
        ]);

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::CLIENT_AUTH), EventContent::fromString(''), $tags);
    }

    public static function createHttpAuth(
        PublicKey $pubkey,
        string $url,
        string $method,
        ?string $payloadHash = null,
    ): Event {
        $tags = [
            Tag::create(TagType::URL, $url),
            Tag::create(TagType::METHOD, $method),
        ];

        if (null !== $payloadHash) {
            $tags[] = Tag::create(TagType::PAYLOAD, $payloadHash);
        }

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::HTTP_AUTH), EventContent::fromString(''), new TagCollection($tags));
    }

    public static function createRumour(
        PublicKey $pubkey,
        string $content,
        TagCollection $recipientTags,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString($content), $recipientTags);
    }

    public static function createDmRelayList(
        PublicKey $pubkey,
        TagCollection $relayTags,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::DM_RELAY_LIST), EventContent::fromString(''), $relayTags);
    }

    public static function createLongformContent(
        PublicKey $pubkey,
        EventContent $content,
        LongformMetadata $metadata,
        ?Timestamp $createdAt = null,
    ): Event {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::LONGFORM_CONTENT), $content, $metadata->toTags(), $createdAt);
    }
}
