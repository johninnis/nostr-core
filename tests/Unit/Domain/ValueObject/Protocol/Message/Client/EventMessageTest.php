<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventMessageTest extends TestCase
{
    public function testGetTypeReturnsEvent(): void
    {
        $message = new EventMessage($this->createEvent());

        $this->assertSame('EVENT', $message->getType());
    }

    public function testGetEventReturnsConstructedEvent(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage($event);

        $this->assertSame($event, $message->getEvent());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage($event);

        $result = $message->toArray();

        $this->assertSame('EVENT', $result[0]);
        $this->assertSame($event->toArray(), $result[1]);
        $this->assertCount(2, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage($event);

        $json = $message->toJson();
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('EVENT', $decoded[0]);
        $this->assertIsArray($decoded[1]);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $event = $this->createEvent();
        $data = ['EVENT', $event->toArray()];

        $message = EventMessage::fromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('EVENT', $message->getType());
        $this->assertSame($event->getPubkey()->toHex(), $message->getEvent()->getPubkey()->toHex());
    }

    public function testFromArrayCapturesRawJson(): void
    {
        $event = $this->createEvent();

        $message = EventMessage::fromArray(['EVENT', $event->toArray()]) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame($event->toJson(), $message->getEvent()->getRawJson());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->assertNull(EventMessage::fromArray(['EVENT']));
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->assertNull(EventMessage::fromArray(['REQ', $this->createEvent()->toArray()]));
    }

    public function testRoundTripPreservesData(): void
    {
        $event = $this->createEvent();
        $original = new EventMessage($event);

        $restored = EventMessage::fromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame($original->getEvent()->getPubkey()->toHex(), $restored->getEvent()->getPubkey()->toHex());
        $this->assertSame($original->getEvent()->getKind()->toInt(), $restored->getEvent()->getKind()->toInt());
        $this->assertSame((string) $original->getEvent()->getContent(), (string) $restored->getEvent()->getContent());
    }

    private static function createPublicKey(): PublicKey
    {
        return PublicKey::fromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid test public key');
    }

    private function createEvent(): Event
    {
        return new Event(
            self::createPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test content'),
        );
    }
}
