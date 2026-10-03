<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClosedMessageTest extends TestCase
{
    public function testGetTypeReturnsClosed(): void
    {
        $message = ClosedMessage::closed(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ReasonPrefix::Error, 'subscription not found');

        $this->assertSame(RelayMessageType::Closed, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = ClosedMessage::closed($subId, ReasonPrefix::Error, 'reason');

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetMessageReturnsConstructedValue(): void
    {
        $message = ClosedMessage::closed(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ReasonPrefix::Error, 'too many subscriptions');

        $this->assertSame('error: too many subscriptions', $message->getMessage());
    }

    public function testGetReasonPrefixReadsTheMachineReadablePrefix(): void
    {
        $message = ClosedMessage::closed(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ReasonPrefix::AuthRequired, 'authentication required');

        $this->assertSame(ReasonPrefix::AuthRequired, $message->getReasonPrefix());
    }

    public function testGetReasonPrefixIsErrorForAPrefixOutsideTheProtocolList(): void
    {
        $message = ClosedMessage::tryFromArray(['CLOSED', 'sub-1', 'unsupported: filter contains unknown elements'])
            ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ReasonPrefix::Error, $message->getReasonPrefix());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = ClosedMessage::closed(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ReasonPrefix::Error, 'shutting down');

        $result = $message->toArray();

        $this->assertSame('CLOSED', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame('error: shutting down', $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = ClosedMessage::closed(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ReasonPrefix::Error, 'reason');

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('CLOSED', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
        $this->assertSame('error: reason', $decoded[2]);
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $data = ['CLOSED', 'sub-1', 'error: subscription closed'];

        $message = ClosedMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Closed, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame('error: subscription closed', $message->getMessage());
    }

    public function testTryFromArrayReturnsNullWithoutTheMessage(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['CLOSED', 'sub-1']));
    }

    public function testTryFromArrayReadsEveryNip01Example(): void
    {
        foreach ([
            'unsupported: filter contains unknown elements',
            'error: could not connect to the database',
            'error: shutting down idle subscription',
        ] as $reason) {
            $this->assertNotNull(ClosedMessage::tryFromArray(['CLOSED', 'sub1', $reason]), $reason);
        }
    }

    public function testAMessageWithoutAPrefixIsReadAsAnErrorAndKeptAsSent(): void
    {
        $message = ClosedMessage::tryFromArray(['CLOSED', 'sub-1', 'closed by relay']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ReasonPrefix::Error, $message->getReasonPrefix());
        $this->assertSame('closed by relay', $message->getMessage());
    }

    public function testAnEmptyMessageIsReadAsAnError(): void
    {
        $message = ClosedMessage::tryFromArray(['CLOSED', 'sub-1', '']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ReasonPrefix::Error, $message->getReasonPrefix());
    }

    public function testTryFromArrayIgnoresATrailingElement(): void
    {
        $message = ClosedMessage::tryFromArray(['CLOSED', 'sub-1', 'error: reason', 'extra']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('error: reason', $message->getMessage());
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
        $original = ClosedMessage::closed(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ReasonPrefix::Error, 'shutting down');

        $restored = ClosedMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertSame($original->getMessage(), $restored->getMessage());
    }

    public function testTryFromArrayRejectsNonStringSubscriptionId(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['CLOSED', 42, 'error: reason']));
    }

    public function testTryFromArrayRejectsNonStringReason(): void
    {
        $this->assertNull(ClosedMessage::tryFromArray(['CLOSED', 'sub-1', ['structured']]));
    }

    public function testClosedWritesThePrefixBeforeTheDetail(): void
    {
        $message = ClosedMessage::closed(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            ReasonPrefix::RateLimited,
            'slow down',
        );

        $this->assertSame('rate-limited: slow down', $message->getMessage());
    }
}
