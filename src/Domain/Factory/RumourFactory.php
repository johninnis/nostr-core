<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Factory;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\EventKindCategory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Content\LongformMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final class RumourFactory
{
    // Deliberate: irreducible event shape; created_at is read directly, not clock-injected — see ADR-0005
    public static function createCustomKind(
        PublicKey $pubkey,
        EventKind $kind,
        EventContent $content,
        ?TagCollection $tags = null,
        ?Timestamp $createdAt = null,
    ): Rumour {
        return new Rumour(
            $pubkey,
            $createdAt ?? Timestamp::now(),
            $kind,
            $tags ?? new TagCollection(),
            $content,
        );
    }

    public static function createTextNote(
        PublicKey $pubkey,
        string $content,
        ?TagCollection $tags = null,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::TEXT_NOTE), EventContent::fromString($content), $tags);
    }

    public static function createMetadata(
        PublicKey $pubkey,
        string $metadata,
        ?TagCollection $tags = null,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::METADATA), EventContent::fromString($metadata), $tags);
    }

    public static function createEncryptedDirectMessage(
        PublicKey $pubkey,
        string $encryptedContent,
        TagCollection $tags,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::ENCRYPTED_DIRECT_MESSAGE), EventContent::fromString($encryptedContent), $tags);
    }

    public static function createEventDeletion(
        PublicKey $pubkey,
        TagCollection $tags,
        string $reason = '',
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::EVENT_DELETION), EventContent::fromString($reason), $tags);
    }

    // Deliberate: irreducible event shape; created_at is read directly, not clock-injected — see ADR-0005
    public static function createFileMetadata(
        PublicKey $pubkey,
        FileMetadata $metadata,
        string $caption = '',
        ?Timestamp $createdAt = null,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::FILE_METADATA), EventContent::fromString($caption), $metadata->toTags(), $createdAt);
    }

    public static function createRepost(PublicKey $pubkey, Event $originalEvent, ?RelayUrl $relayHint = null): Rumour
    {
        $tags = new TagCollection([
            Tag::event($originalEvent->getId(), $relayHint),
            Tag::pubkey($originalEvent->getPubkey()),
        ]);

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::REPOST), EventContent::fromString(''), $tags);
    }

    public static function createReaction(
        PublicKey $pubkey,
        Event $targetEvent,
        string $reaction = '+',
    ): Rumour {
        $tags = [
            Tag::event($targetEvent->getId()),
            Tag::pubkey($targetEvent->getPubkey()),
            Tag::create(TagType::PARENT_KIND, (string) $targetEvent->getKind()->toInt()),
        ];

        $coordinate = self::addressableCoordinate($targetEvent);
        if (null !== $coordinate) {
            $tags[] = Tag::create(TagType::ADDRESSABLE, (string) $coordinate);
        }

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::REACTION), EventContent::fromString($reaction), new TagCollection($tags));
    }

    private static function addressableCoordinate(Event $event): ?EventCoordinate
    {
        if (EventKindCategory::Addressable !== $event->getKind()->category()) {
            return null;
        }

        $identifier = $event->getTags()->getFirstValueByType(TagType::identifier());
        if (null === $identifier) {
            return null;
        }

        return EventCoordinate::tryFrom($event->getKind(), $event->getPubkey(), $identifier);
    }

    public static function createFollowList(PublicKey $pubkey, TagCollection $followTags): Rumour
    {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::FOLLOW_LIST), EventContent::fromString(''), $followTags);
    }

    public static function createRelayList(PublicKey $pubkey, TagCollection $relayTags): Rumour
    {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::RELAY_LIST), EventContent::fromString(''), $relayTags);
    }

    public static function createMuteList(PublicKey $pubkey, TagCollection $muteTags): Rumour
    {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::MUTE_LIST), EventContent::fromString(''), $muteTags);
    }

    public static function createAuth(
        PublicKey $pubkey,
        RelayUrl $relayUrl,
        string $challenge,
    ): Rumour {
        $tags = new TagCollection([
            Tag::create(TagType::RELAY, (string) $relayUrl),
            Tag::create(TagType::CHALLENGE, $challenge),
        ]);

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::CLIENT_AUTH), EventContent::fromString(''), $tags);
    }

    public static function createHttpAuth(PublicKey $pubkey, Nip98Request $request, ?Timestamp $createdAt = null): Rumour
    {
        $tags = [
            Tag::create(TagType::URL, $request->getUrl()),
            Tag::create(TagType::METHOD, $request->getMethod()),
        ];

        $bodyHash = $request->getBodyHash();
        if (null !== $bodyHash) {
            $tags[] = Tag::create(TagType::PAYLOAD, $bodyHash);
        }

        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::HTTP_AUTH), EventContent::fromString(''), new TagCollection($tags), $createdAt);
    }

    public static function createPrivateMessage(
        PublicKey $pubkey,
        string $content,
        TagCollection $recipientTags,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString($content), $recipientTags);
    }

    public static function createDmRelayList(
        PublicKey $pubkey,
        TagCollection $relayTags,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::DM_RELAY_LIST), EventContent::fromString(''), $relayTags);
    }

    // Deliberate: irreducible event shape; created_at is read directly, not clock-injected — see ADR-0005
    public static function createLongformContent(
        PublicKey $pubkey,
        EventContent $content,
        LongformMetadata $metadata,
        ?Timestamp $createdAt = null,
    ): Rumour {
        return self::createCustomKind($pubkey, EventKind::fromInt(EventKind::LONGFORM_CONTENT), $content, $metadata->toTags(), $createdAt);
    }
}
