<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Factory;

use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class EventFactoryTest extends TestCase
{
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->keyPair = KeyPair::generate();
    }

    public function testCanCreateTextNote(): void
    {
        $event = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'Hello Nostr!'
        );

        $this->assertTrue($event->getKind()->equals(EventKind::textNote()));
        $this->assertSame('Hello Nostr!', (string) $event->getContent());
        $this->assertTrue($event->getTags()->isEmpty());
    }

    public function testCanCreateMetadata(): void
    {
        $metadata = '{"name":"Alice","about":"Nostr user"}';
        $event = EventFactory::createMetadata(
            $this->keyPair->getPublicKey(),
            $metadata
        );

        $this->assertTrue($event->getKind()->equals(EventKind::metadata()));
        $this->assertSame($metadata, (string) $event->getContent());
    }

    public function testCanCreateFollowList(): void
    {
        $tags = new TagCollection([Tag::pubkey('follow-pubkey')]);
        $event = EventFactory::createFollowList(
            $this->keyPair->getPublicKey(),
            $tags
        );

        $this->assertTrue($event->getKind()->equals(EventKind::followList()));
        $this->assertTrue($event->getTags()->equals($tags));
    }

    public function testCanCreateEncryptedDirectMessage(): void
    {
        $encryptedContent = 'encrypted-content';
        $tags = new TagCollection([Tag::pubkey('recipient-pubkey')]);

        $event = EventFactory::createEncryptedDirectMessage(
            $this->keyPair->getPublicKey(),
            $encryptedContent,
            $tags
        );

        $this->assertTrue($event->getKind()->equals(EventKind::encryptedDirectMessage()));
        $this->assertSame($encryptedContent, (string) $event->getContent());
        $this->assertTrue($event->getTags()->equals($tags));
    }

    public function testCanCreateEventDeletion(): void
    {
        $tags = new TagCollection([Tag::event('event-to-delete')]);
        $reason = 'spam';

        $event = EventFactory::createEventDeletion(
            $this->keyPair->getPublicKey(),
            $tags,
            $reason
        );

        $this->assertTrue($event->getKind()->equals(EventKind::eventDeletion()));
        $this->assertSame($reason, (string) $event->getContent());
        $this->assertTrue($event->getTags()->equals($tags));
    }

    public function testCanCreateCustomKind(): void
    {
        $customKind = EventKind::fromInt(1000);
        $content = EventContent::fromString('custom content');

        $event = EventFactory::createCustomKind(
            $this->keyPair->getPublicKey(),
            $customKind,
            $content
        );

        $this->assertTrue($event->getKind()->equals($customKind));
        $this->assertTrue($event->getContent()->equals($content));
    }

    public function testCanCreateAuth(): void
    {
        $relayUrl = RelayUrl::fromString('wss://relay.example.com');
        $this->assertNotNull($relayUrl);
        $challenge = 'test-challenge-string';

        $event = EventFactory::createAuth(
            $this->keyPair->getPublicKey(),
            $relayUrl,
            $challenge
        );

        $this->assertSame(EventKind::CLIENT_AUTH, $event->getKind()->toInt());
        $this->assertSame('', (string) $event->getContent());

        $relayTags = $event->getTags()->findByType(TagType::fromString('relay'));
        $challengeTags = $event->getTags()->findByType(TagType::fromString('challenge'));

        $this->assertCount(1, $relayTags);
        $this->assertSame('wss://relay.example.com', $relayTags[0]->getValue());
        $this->assertCount(1, $challengeTags);
        $this->assertSame('test-challenge-string', $challengeTags[0]->getValue());
    }

    public function testFactoryMethodsCreateEventsWithReasonableTimestamps(): void
    {
        $event = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'test'
        );

        $this->assertTrue($event->getCreatedAt()->isReasonable());
    }

    public function testCanCreateRepost(): void
    {
        $originalEvent = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'Original post'
        )->sign($this->keyPair->getPrivateKey());

        $repostingKeyPair = KeyPair::generate();
        $repost = EventFactory::createRepost($repostingKeyPair->getPublicKey(), $originalEvent);

        $this->assertTrue($repost->getKind()->equals(EventKind::repost()));
        $this->assertSame('', (string) $repost->getContent());

        $eTags = $repost->getTags()->findByType(TagType::event());
        $pTags = $repost->getTags()->findByType(TagType::pubkey());

        $this->assertCount(1, $eTags);
        $this->assertSame($originalEvent->getId()->toHex(), $eTags[0]->getValue());
        $this->assertCount(1, $pTags);
        $this->assertSame($originalEvent->getPubkey()->toHex(), $pTags[0]->getValue());
    }

    public function testCanCreateReaction(): void
    {
        $targetEvent = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'Target post'
        )->sign($this->keyPair->getPrivateKey());

        $reactingKeyPair = KeyPair::generate();
        $reaction = EventFactory::createReaction($reactingKeyPair->getPublicKey(), $targetEvent);

        $this->assertTrue($reaction->getKind()->equals(EventKind::reaction()));
        $this->assertSame('+', (string) $reaction->getContent());

        $eTags = $reaction->getTags()->findByType(TagType::event());
        $pTags = $reaction->getTags()->findByType(TagType::pubkey());

        $this->assertCount(1, $eTags);
        $this->assertSame($targetEvent->getId()->toHex(), $eTags[0]->getValue());
        $this->assertCount(1, $pTags);
        $this->assertSame($targetEvent->getPubkey()->toHex(), $pTags[0]->getValue());
    }

    public function testCanCreateReactionWithCustomContent(): void
    {
        $targetEvent = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'Target post'
        )->sign($this->keyPair->getPrivateKey());

        $reaction = EventFactory::createReaction($this->keyPair->getPublicKey(), $targetEvent, '-');

        $this->assertSame('-', (string) $reaction->getContent());
    }

    public function testCanCreateRelayList(): void
    {
        $relayTags = new TagCollection([
            Tag::fromArray(['r', 'wss://relay1.example.com']),
            Tag::fromArray(['r', 'wss://relay2.example.com', 'read']),
        ]);

        $event = EventFactory::createRelayList($this->keyPair->getPublicKey(), $relayTags);

        $this->assertTrue($event->getKind()->equals(EventKind::relayList()));
        $this->assertSame('', (string) $event->getContent());
        $this->assertTrue($event->getTags()->equals($relayTags));
    }

    public function testCanCreateMuteList(): void
    {
        $muteTags = new TagCollection([
            Tag::pubkey(str_repeat('a', 64)),
            Tag::hashtag('spam'),
        ]);

        $event = EventFactory::createMuteList($this->keyPair->getPublicKey(), $muteTags);

        $this->assertTrue($event->getKind()->equals(EventKind::muteList()));
        $this->assertSame('', (string) $event->getContent());
        $this->assertTrue($event->getTags()->equals($muteTags));
    }

    public function testCanCreateLongformContentWithMinimalFields(): void
    {
        $content = EventContent::fromString('# My Article\n\nSome content here.');
        $event = EventFactory::createLongformContent(
            $this->keyPair->getPublicKey(),
            $content,
            'my-article'
        );

        $this->assertTrue($event->getKind()->equals(EventKind::longformContent()));
        $this->assertTrue($event->getContent()->equals($content));

        $dTags = $event->getTags()->findByType(TagType::identifier());
        $this->assertCount(1, $dTags);
        $this->assertSame('my-article', $dTags[0]->getValue());
    }

    public function testCanCreateLongformContentWithAllFields(): void
    {
        $content = EventContent::fromString('Article body');
        $publishedAt = Timestamp::fromInt(1700000000);
        $createdAt = Timestamp::fromInt(1700000100);

        $event = EventFactory::createLongformContent(
            $this->keyPair->getPublicKey(),
            $content,
            'full-article',
            'My Full Article',
            'A summary of the article',
            'https://example.com/image.jpg',
            $publishedAt,
            ['nostr', 'bitcoin'],
            $createdAt,
        );

        $this->assertTrue($event->getKind()->equals(EventKind::longformContent()));
        $this->assertSame(1700000100, $event->getCreatedAt()->toInt());

        $tags = $event->getTags();
        $dTags = $tags->findByType(TagType::identifier());
        $this->assertCount(1, $dTags);
        $this->assertSame('full-article', $dTags[0]->getValue());

        $titleTags = $tags->findByType(TagType::fromString('title'));
        $this->assertCount(1, $titleTags);
        $this->assertSame('My Full Article', $titleTags[0]->getValue());

        $summaryTags = $tags->findByType(TagType::fromString('summary'));
        $this->assertCount(1, $summaryTags);
        $this->assertSame('A summary of the article', $summaryTags[0]->getValue());

        $imageTags = $tags->findByType(TagType::fromString('image'));
        $this->assertCount(1, $imageTags);
        $this->assertSame('https://example.com/image.jpg', $imageTags[0]->getValue());

        $publishedAtTags = $tags->findByType(TagType::fromString('published_at'));
        $this->assertCount(1, $publishedAtTags);
        $this->assertSame('1700000000', $publishedAtTags[0]->getValue());

        $hashtagTags = $tags->findByType(TagType::hashtag());
        $this->assertCount(2, $hashtagTags);
    }

    public function testCanCreateTextNoteWithTags(): void
    {
        $tags = new TagCollection([Tag::hashtag('nostr')]);
        $event = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'Hello with tags!',
            $tags
        );

        $this->assertTrue($event->getTags()->equals($tags));
    }

    public function testCanCreateMetadataWithTags(): void
    {
        $tags = new TagCollection([Tag::fromArray(['alt', 'metadata event'])]);
        $event = EventFactory::createMetadata(
            $this->keyPair->getPublicKey(),
            '{"name":"Alice"}',
            $tags
        );

        $this->assertTrue($event->getTags()->equals($tags));
    }

    public function testCanCreateEventDeletionWithDefaultReason(): void
    {
        $tags = new TagCollection([Tag::event('event-to-delete')]);
        $event = EventFactory::createEventDeletion(
            $this->keyPair->getPublicKey(),
            $tags,
        );

        $this->assertSame('', (string) $event->getContent());
    }

    public function testCanCreateCustomKindWithTimestamp(): void
    {
        $customTimestamp = Timestamp::fromInt(1700000000);
        $event = EventFactory::createCustomKind(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(30000),
            EventContent::fromString('custom'),
            null,
            $customTimestamp
        );

        $this->assertSame(1700000000, $event->getCreatedAt()->toInt());
    }

    public function testCanCreateCustomKindWithTags(): void
    {
        $tags = new TagCollection([Tag::identifier('test-id')]);
        $event = EventFactory::createCustomKind(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(30000),
            EventContent::fromString('custom'),
            $tags
        );

        $this->assertTrue($event->getTags()->equals($tags));
    }
}
