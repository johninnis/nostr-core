<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CloseMessageTest extends TestCase
{
    public function testGetTypeReturnsClose(): void
    {
        $message = new CloseMessage(SubscriptionId::fromString('sub-1'));

        $this->assertSame('CLOSE', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1');
        $message = new CloseMessage($subId);

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new CloseMessage(SubscriptionId::fromString('sub-1'));

        $result = $message->toArray();

        $this->assertSame(['CLOSE', 'sub-1'], $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new CloseMessage(SubscriptionId::fromString('sub-1'));

        $json = $message->toJson();

        $this->assertSame('["CLOSE","sub-1"]', $json);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $message = CloseMessage::fromArray(['CLOSE', 'sub-1']);

        $this->assertSame('CLOSE', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CloseMessage::fromArray(['CLOSE']);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CloseMessage::fromArray(['EVENT', 'sub-1']);
    }

    public function testFromArrayThrowsOnTooManyElements(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CloseMessage::fromArray(['CLOSE', 'sub-1', 'extra']);
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new CloseMessage(SubscriptionId::fromString('my-subscription'));

        $restored = CloseMessage::fromArray($original->toArray());

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
    }
}
