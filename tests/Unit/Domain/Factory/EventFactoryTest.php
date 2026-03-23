<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Factory;

use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
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

    public function testFactoryMethodsCreateEventsWithReasonableTimestamps(): void
    {
        $event = EventFactory::createTextNote(
            $this->keyPair->getPublicKey(),
            'test'
        );

        $this->assertTrue($event->getCreatedAt()->isReasonable());
    }
}
