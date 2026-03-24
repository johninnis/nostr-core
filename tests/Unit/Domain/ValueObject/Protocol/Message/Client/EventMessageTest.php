<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventMessageTest extends TestCase
{
    private Event $event;

    protected function setUp(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr!')
        );
        $this->event = $event->sign($keyPair->getPrivateKey());
    }

    public function testCanCreateEventMessage(): void
    {
        $message = new EventMessage($this->event);

        $this->assertSame('EVENT', $message->getType());
        $this->assertTrue($message->getEvent()->getId()->equals($this->event->getId()));
    }

    public function testCanConvertToArray(): void
    {
        $message = new EventMessage($this->event);
        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertSame('EVENT', $array[0]);
        $this->assertIsArray($array[1]);
        $this->assertSame($this->event->getId()->toHex(), $array[1]['id']);
    }

    public function testCanCreateFromArray(): void
    {
        $eventData = $this->event->toArray();
        $data = ['EVENT', $eventData];

        $message = EventMessage::fromArray($data);

        $this->assertInstanceOf(EventMessage::class, $message);
        $this->assertSame('EVENT', $message->getType());
        $this->assertTrue($message->getEvent()->getId()->equals($this->event->getId()));
    }

    public function testFromArrayThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid EVENT message format');

        EventMessage::fromArray(['INVALID', 'data']);
    }

    public function testFromArrayThrowsExceptionForWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid EVENT message format');

        EventMessage::fromArray(['EVENT']);
    }
}
