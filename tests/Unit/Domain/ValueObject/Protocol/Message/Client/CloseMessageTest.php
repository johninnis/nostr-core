<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CloseMessageTest extends TestCase
{
    public function testGetTypeReturnsClose(): void
    {
        $message = new CloseMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame('CLOSE', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = new CloseMessage($subId);

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new CloseMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $result = $message->toArray();

        $this->assertSame(['CLOSE', 'sub-1'], $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new CloseMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $json = $message->toJson();

        $this->assertSame('["CLOSE","sub-1"]', $json);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $message = CloseMessage::fromArray(['CLOSE', 'sub-1']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('CLOSE', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->assertNull(CloseMessage::fromArray(['CLOSE']));
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->assertNull(CloseMessage::fromArray(['EVENT', 'sub-1']));
    }

    public function testFromArrayThrowsOnTooManyElements(): void
    {
        $this->assertNull(CloseMessage::fromArray(['CLOSE', 'sub-1', 'extra']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new CloseMessage(SubscriptionId::fromString('my-subscription') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $restored = CloseMessage::fromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
    }
}
