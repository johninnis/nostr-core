<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EoseMessageTest extends TestCase
{
    public function testGetTypeReturnsEose(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame(RelayMessageType::Eose, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = new EoseMessage($subId);

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame(['EOSE', 'sub-1'], $message->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame('["EOSE","sub-1"]', $message->toJson());
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $message = EoseMessage::tryFromArray(['EOSE', 'sub-1']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Eose, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(EoseMessage::tryFromArray(['EOSE']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(EoseMessage::tryFromArray(['CLOSED', 'sub-1']));
    }

    public function testTryFromArrayReturnsNullOnTooManyElements(): void
    {
        $this->assertNull(EoseMessage::tryFromArray(['EOSE', 'sub-1', 'extra']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new EoseMessage(SubscriptionId::tryFromString('my-subscription') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $restored = EoseMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
    }
}
