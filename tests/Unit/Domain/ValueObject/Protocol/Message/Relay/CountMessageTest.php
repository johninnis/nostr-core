<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CountMessageTest extends TestCase
{
    public function testGetTypeReturnsCount(): void
    {
        $message = new CountMessage(SubscriptionId::fromString('sub1'), 42);

        $this->assertSame('COUNT', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub1');
        $message = new CountMessage($subId, 10);

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetCountReturnsConstructedValue(): void
    {
        $message = new CountMessage(SubscriptionId::fromString('sub1'), 42);

        $this->assertSame(42, $message->getCount());
    }

    public function testCountCanBeZero(): void
    {
        $message = new CountMessage(SubscriptionId::fromString('sub1'), 0);

        $this->assertSame(0, $message->getCount());
    }

    public function testThrowsOnNegativeCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Count cannot be negative');

        new CountMessage(SubscriptionId::fromString('sub1'), -1);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new CountMessage(SubscriptionId::fromString('sub1'), 42);

        $result = $message->toArray();

        $this->assertSame('COUNT', $result[0]);
        $this->assertSame('sub1', $result[1]);
        $this->assertSame(['count' => 42], $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new CountMessage(SubscriptionId::fromString('sub1'), 42);

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $array = (array) $decoded;
        $this->assertSame('COUNT', $array[0]);
        $this->assertSame('sub1', $array[1]);
        $this->assertSame(['count' => 42], $array[2]);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $data = ['COUNT', 'sub1', ['count' => 42]];

        $message = CountMessage::fromArray($data);

        $this->assertSame('COUNT', $message->getType());
        $this->assertSame('sub1', (string) $message->getSubscriptionId());
        $this->assertSame(42, $message->getCount());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CountMessage::fromArray(['COUNT', 'sub1']);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CountMessage::fromArray(['EVENT', 'sub1', ['count' => 42]]);
    }

    public function testFromArrayThrowsOnMissingCountKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid COUNT message payload');

        CountMessage::fromArray(['COUNT', 'sub1', ['total' => 42]]);
    }

    public function testFromArrayThrowsOnNonArrayPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid COUNT message payload');

        CountMessage::fromArray(['COUNT', 'sub1', 42]);
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new CountMessage(SubscriptionId::fromString('test-sub'), 100);

        $restored = CountMessage::fromArray($original->toArray());

        $this->assertSame((string) $original->getSubscriptionId(), (string) $restored->getSubscriptionId());
        $this->assertSame($original->getCount(), $restored->getCount());
    }
}
