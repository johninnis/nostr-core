<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventMessageTest extends TestCase
{
    public function testGetTypeReturnsEvent(): void
    {
        $message = new EventMessage(
            SubscriptionId::fromString('sub-1'),
            $this->createEvent(),
        );

        $this->assertSame('EVENT', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1');
        $message = new EventMessage($subId, $this->createEvent());

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetEventReturnsConstructedEvent(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage(SubscriptionId::fromString('sub-1'), $event);

        $this->assertSame($event, $message->getEvent());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage(SubscriptionId::fromString('sub-1'), $event);

        $result = $message->toArray();

        $this->assertSame('EVENT', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame($event->toArray(), $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new EventMessage(
            SubscriptionId::fromString('sub-1'),
            $this->createEvent(),
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('EVENT', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
        $this->assertIsArray($decoded[2]);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $event = $this->createEvent();
        $data = ['EVENT', 'sub-1', $event->toArray()];

        $message = EventMessage::fromArray($data);

        $this->assertSame('EVENT', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventMessage::fromArray(['EVENT', 'sub-1']);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventMessage::fromArray(['OK', 'sub-1', $this->createEvent()->toArray()]);
    }

    public function testRoundTripPreservesData(): void
    {
        $event = $this->createEvent();
        $original = new EventMessage(SubscriptionId::fromString('sub-1'), $event);

        $restored = EventMessage::fromArray($original->toArray());

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertSame(
            $original->getEvent()->getPubkey()->toHex(),
            $restored->getEvent()->getPubkey()->toHex()
        );
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
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test content'),
        );
    }
}
