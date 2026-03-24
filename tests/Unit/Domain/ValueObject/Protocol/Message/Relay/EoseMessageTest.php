<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EoseMessageTest extends TestCase
{
    public function testGetTypeReturnsEose(): void
    {
        $message = new EoseMessage(SubscriptionId::fromString('sub-1'));

        $this->assertSame('EOSE', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1');
        $message = new EoseMessage($subId);

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new EoseMessage(SubscriptionId::fromString('sub-1'));

        $this->assertSame(['EOSE', 'sub-1'], $message->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new EoseMessage(SubscriptionId::fromString('sub-1'));

        $this->assertSame('["EOSE","sub-1"]', $message->toJson());
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $message = EoseMessage::fromArray(['EOSE', 'sub-1']);

        $this->assertSame('EOSE', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EoseMessage::fromArray(['EOSE']);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EoseMessage::fromArray(['CLOSED', 'sub-1']);
    }

    public function testFromArrayThrowsOnTooManyElements(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EoseMessage::fromArray(['EOSE', 'sub-1', 'extra']);
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new EoseMessage(SubscriptionId::fromString('my-subscription'));

        $restored = EoseMessage::fromArray($original->toArray());

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
    }
}
