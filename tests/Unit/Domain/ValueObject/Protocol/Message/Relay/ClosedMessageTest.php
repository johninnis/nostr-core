<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClosedMessageTest extends TestCase
{
    public function testGetTypeReturnsClosed(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            'error: subscription not found',
        );

        $this->assertSame(RelayMessageType::Closed, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = new ClosedMessage($subId, 'reason');

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetMessageReturnsConstructedValue(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            'error: too many subscriptions',
        );

        $this->assertSame('error: too many subscriptions', $message->getMessage());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new ClosedMessage(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
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
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            'reason',
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('CLOSED', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
        $this->assertSame('reason', $decoded[2]);
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $data = ['CLOSED', 'sub-1', 'error: subscription closed'];

        $message = ClosedMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Closed, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame('error: subscription closed', $message->getMessage());
    }

    public function testTryFromArrayWithoutMessageUsesEmptyString(): void
    {
        $data = ['CLOSED', 'sub-1'];

        $message = ClosedMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('', $message->getMessage());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['CLOSED']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['EOSE', 'sub-1', 'reason']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new ClosedMessage(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            'error: shutting down',
        );

        $restored = ClosedMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertSame($original->getMessage(), $restored->getMessage());
    }

    public function testTryFromArrayRejectsNonStringSubscriptionId(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['CLOSED', 42, 'reason']));
    }

    public function testTryFromArrayRejectsNonStringReason(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['CLOSED', 'sub-1', ['structured']]));
    }
}
