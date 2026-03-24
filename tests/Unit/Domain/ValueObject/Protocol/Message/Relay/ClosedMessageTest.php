<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClosedMessageTest extends TestCase
{
    public function testGetTypeReturnsClosed(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::fromString('sub-1'),
            'error: subscription not found',
        );

        $this->assertSame('CLOSED', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1');
        $message = new ClosedMessage($subId, 'reason');

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetMessageReturnsConstructedValue(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::fromString('sub-1'),
            'error: too many subscriptions',
        );

        $this->assertSame('error: too many subscriptions', $message->getMessage());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::fromString('sub-1'),
            'shutting down',
        );

        $result = $message->toArray();

        $this->assertSame('CLOSED', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame('shutting down', $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::fromString('sub-1'),
            'reason',
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('CLOSED', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
        $this->assertSame('reason', $decoded[2]);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $data = ['CLOSED', 'sub-1', 'error: subscription closed'];

        $message = ClosedMessage::fromArray($data);

        $this->assertSame('CLOSED', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame('error: subscription closed', $message->getMessage());
    }

    public function testFromArrayWithoutMessageUsesEmptyString(): void
    {
        $data = ['CLOSED', 'sub-1'];

        $message = ClosedMessage::fromArray($data);

        $this->assertSame('', $message->getMessage());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClosedMessage::fromArray(['CLOSED']);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClosedMessage::fromArray(['EOSE', 'sub-1', 'reason']);
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new ClosedMessage(
            SubscriptionId::fromString('sub-1'),
            'error: shutting down',
        );

        $restored = ClosedMessage::fromArray($original->toArray());

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertSame($original->getMessage(), $restored->getMessage());
    }
}
