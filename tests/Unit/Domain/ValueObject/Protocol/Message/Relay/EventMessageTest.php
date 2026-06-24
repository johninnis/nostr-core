<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventMessageTest extends TestCase
{
    public function testGetTypeReturnsEvent(): void
    {
        $message = new EventMessage(
            SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            $this->createEvent(),
        );

        $this->assertSame('EVENT', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = new EventMessage($subId, $this->createEvent());

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetEventReturnsConstructedEvent(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), $event);

        $this->assertSame($event, $message->getEvent());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $event = $this->createEvent();
        $message = new EventMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), $event);

        $result = $message->toArray();

        $this->assertSame('EVENT', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame($event->toArray(), $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new EventMessage(
            SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            $this->createEvent(),
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('EVENT', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
        $this->assertIsArray($decoded[2]);
    }

    public function testToJsonSplicesRawJsonWhenEventCarriesIt(): void
    {
        $rawEvent = json_encode(
            $this->createEvent()->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $message = new EventMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), Event::fromJson($rawEvent) ?? throw new RuntimeException('Expected a valid event'));

        $this->assertSame('["EVENT","sub-1",'.$rawEvent.']', $message->toJson());
    }

    public function testToJsonIsByteIdenticalWithOrWithoutRawJson(): void
    {
        $event = $this->createEvent();
        $rawEvent = json_encode(
            $event->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $subscriptionId = SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $withoutRaw = new EventMessage($subscriptionId, $event);
        $withRaw = new EventMessage($subscriptionId, Event::fromJson($rawEvent) ?? throw new RuntimeException('Expected a valid event'));

        $this->assertSame($withoutRaw->toJson(), $withRaw->toJson());
    }

    public function testPreSerialisedJsonSplicesRawEventOrReturnsNull(): void
    {
        $rawEvent = json_encode(
            $this->createEvent()->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $stored = new EventMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), Event::fromJson($rawEvent) ?? throw new RuntimeException('Expected a valid event'));
        $fresh = new EventMessage(SubscriptionId::fromString('sub-1'), $this->createEvent());

        self::assertSame('["EVENT","sub-1",'.$rawEvent.']', $stored->preSerialisedJson());
        self::assertNull($fresh->preSerialisedJson());
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $event = $this->createEvent();
        $data = ['EVENT', 'sub-1', $event->toArray()];

        $message = EventMessage::fromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('EVENT', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->assertNull(EventMessage::fromArray(['EVENT', 'sub-1']));
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->assertNull(EventMessage::fromArray(['OK', 'sub-1', $this->createEvent()->toArray()]));
    }

    public function testRoundTripPreservesData(): void
    {
        $event = $this->createEvent();
        $original = new EventMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), $event);

        $restored = EventMessage::fromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

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
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test content'),
        );
    }
}
