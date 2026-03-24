<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Factory;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final class EventFactory
{
    public static function createTextNote(
        PublicKey $pubkey,
        string $content,
        ?TagCollection $tags = null,
    ): Event {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::textNote(),
            $tags ?? TagCollection::empty(),
            EventContent::fromString($content)
        );
    }

    public static function createMetadata(
        PublicKey $pubkey,
        string $metadata,
        ?TagCollection $tags = null,
    ): Event {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::metadata(),
            $tags ?? TagCollection::empty(),
            EventContent::fromString($metadata)
        );
    }

    public static function createEncryptedDirectMessage(
        PublicKey $pubkey,
        string $encryptedContent,
        TagCollection $tags,
    ): Event {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::encryptedDirectMessage(),
            $tags,
            EventContent::fromString($encryptedContent)
        );
    }

    public static function createEventDeletion(
        PublicKey $pubkey,
        TagCollection $tags,
        string $reason = '',
    ): Event {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::eventDeletion(),
            $tags,
            EventContent::fromString($reason)
        );
    }

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
            $content
        );
    }

    public static function createRepost(PublicKey $pubkey, Event $originalEvent): Event
    {
        $tags = new TagCollection([
            Tag::event($originalEvent->getId()->toHex()),
            Tag::pubkey($originalEvent->getPubkey()->toHex()),
        ]);

        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::repost(),
            $tags,
            EventContent::fromString('')
        );
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

        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::reaction(),
            $tags,
            EventContent::fromString($reaction)
        );
    }

    public static function createFollowList(PublicKey $pubkey, TagCollection $followTags): Event
    {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::followList(),
            $followTags,
            EventContent::fromString('')
        );
    }

    public static function createRelayList(PublicKey $pubkey, TagCollection $relayTags): Event
    {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::relayList(),
            $relayTags,
            EventContent::fromString('')
        );
    }

    public static function createMuteList(PublicKey $pubkey, TagCollection $muteTags): Event
    {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::muteList(),
            $muteTags,
            EventContent::fromString('')
        );
    }

    public static function createAuth(
        PublicKey $pubkey,
        RelayUrl $relayUrl,
        string $challenge,
    ): Event {
        return new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::clientAuth(),
            new TagCollection([
                Tag::fromArray(['relay', (string) $relayUrl]),
                Tag::fromArray(['challenge', $challenge]),
            ]),
            EventContent::fromString('')
        );
    }

    public static function createLongformContent(
        PublicKey $pubkey,
        EventContent $content,
        string $identifier,
        ?string $title = null,
        ?string $summary = null,
        ?string $image = null,
        ?Timestamp $publishedAt = null,
        array $hashtags = [],
        ?Timestamp $createdAt = null,
    ): Event {
        $tags = [Tag::identifier($identifier)];

        if (null !== $title) {
            $tags[] = Tag::fromArray(['title', $title]);
        }

        if (null !== $summary) {
            $tags[] = Tag::fromArray(['summary', $summary]);
        }

        if (null !== $image) {
            $tags[] = Tag::fromArray(['image', $image]);
        }

        if (null !== $publishedAt) {
            $tags[] = Tag::fromArray(['published_at', (string) $publishedAt->toInt()]);
        }

        foreach ($hashtags as $hashtag) {
            $tags[] = Tag::hashtag($hashtag);
        }

        return new Event(
            $pubkey,
            $createdAt ?? Timestamp::now(),
            EventKind::longformContent(),
            new TagCollection($tags),
            $content
        );
    }
}
