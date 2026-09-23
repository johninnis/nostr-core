<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\EventCount;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CountMessageTest extends TestCase
{
    public function testGetTypeReturnsCount(): void
    {
        $message = new CountMessage(self::subscriptionId('sub1'), EventCount::exact(42));

        $this->assertSame(RelayMessageType::Count, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = self::subscriptionId('sub1');
        $message = new CountMessage($subId, EventCount::exact(10));

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetCountReturnsConstructedValue(): void
    {
        $message = new CountMessage(self::subscriptionId('sub1'), EventCount::exact(42));

        $this->assertSame(42, $message->getCount()->toInt());
    }

    public function testToArrayOmitsTheApproximateKeyForAnExactCount(): void
    {
        $message = new CountMessage(self::subscriptionId('sub1'), EventCount::exact(42));

        $result = $message->toArray();

        $this->assertSame('COUNT', $result[0]);
        $this->assertSame('sub1', $result[1]);
        $this->assertSame(['count' => 42], $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToArrayMarksAnApproximateCount(): void
    {
        $message = new CountMessage(self::subscriptionId('sub1'), EventCount::approximate(1000));

        $this->assertSame(['count' => 1000, 'approximate' => true], $message->toArray()[2]);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new CountMessage(self::subscriptionId('sub1'), EventCount::exact(42));

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $array = (array) $decoded;
        $this->assertSame('COUNT', $array[0]);
        $this->assertSame('sub1', $array[1]);
        $this->assertSame(['count' => 42], $array[2]);
    }

    public function testTryFromArrayCreatesAnExactCount(): void
    {
        $message = CountMessage::tryFromArray(['COUNT', 'sub1', ['count' => 42]]) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Count, $message->type());
        $this->assertSame('sub1', (string) $message->getSubscriptionId());
        $this->assertSame(42, $message->getCount()->toInt());
        $this->assertFalse($message->getCount()->isApproximate());
    }

    public function testTryFromArrayReadsTheApproximateFlag(): void
    {
        $message = CountMessage::tryFromArray(['COUNT', 'sub1', ['count' => 1000, 'approximate' => true]]) ?? throw new RuntimeException('Expected a valid message');

        $this->assertTrue($message->getCount()->isApproximate());
    }

    public function testTryFromArrayTreatsAnExplicitFalseAsExact(): void
    {
        $message = CountMessage::tryFromArray(['COUNT', 'sub1', ['count' => 42, 'approximate' => false]]) ?? throw new RuntimeException('Expected a valid message');

        $this->assertFalse($message->getCount()->isApproximate());
    }

    public function testTryFromArrayReturnsNullOnANonBooleanApproximateFlag(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['COUNT', 'sub1', ['count' => 42, 'approximate' => 'yes']]));
    }

    public function testTryFromArrayReturnsNullOnANegativeCount(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['COUNT', 'sub1', ['count' => -1]]));
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['COUNT', 'sub1']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['EVENT', 'sub1', ['count' => 42]]));
    }

    public function testTryFromArrayReturnsNullOnMissingCountKey(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['COUNT', 'sub1', ['total' => 42]]));
    }

    public function testTryFromArrayReturnsNullOnNonArrayPayload(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['COUNT', 'sub1', 42]));
    }

    public function testRoundTripPreservesAnExactCount(): void
    {
        $original = new CountMessage(self::subscriptionId('test-sub'), EventCount::exact(100));

        $restored = CountMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame((string) $original->getSubscriptionId(), (string) $restored->getSubscriptionId());
        $this->assertSame(100, $restored->getCount()->toInt());
        $this->assertFalse($restored->getCount()->isApproximate());
    }

    public function testRoundTripPreservesAnApproximateCount(): void
    {
        $original = new CountMessage(self::subscriptionId('test-sub'), EventCount::approximate(1000));

        $restored = CountMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(1000, $restored->getCount()->toInt());
        $this->assertTrue($restored->getCount()->isApproximate());
    }

    private static function subscriptionId(string $id): SubscriptionId
    {
        return SubscriptionId::tryFromString($id) ?? throw new RuntimeException('Expected a valid subscription ID');
    }
}
