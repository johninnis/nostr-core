<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OkMessageTest extends TestCase
{
    private const VALID_EVENT_ID_HEX = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testGetTypeReturnsOk(): void
    {
        $message = new OkMessage(self::createEventId(), true);

        $this->assertSame('OK', $message->getType());
    }

    public function testGetEventIdReturnsConstructedValue(): void
    {
        $eventId = self::createEventId();
        $message = new OkMessage($eventId, true);

        $this->assertTrue($eventId->equals($message->getEventId()));
    }

    public function testIsAcceptedReturnsTrueWhenAccepted(): void
    {
        $message = new OkMessage(self::createEventId(), true);

        $this->assertTrue($message->isAccepted());
    }

    public function testIsAcceptedReturnsFalseWhenRejected(): void
    {
        $message = new OkMessage(self::createEventId(), false);

        $this->assertFalse($message->isAccepted());
    }

    public function testGetMessageReturnsEmptyStringByDefault(): void
    {
        $message = new OkMessage(self::createEventId(), true);

        $this->assertSame('', $message->getMessage());
    }

    public function testGetMessageReturnsConstructedValue(): void
    {
        $message = new OkMessage(
            self::createEventId(),
            false,
            'duplicate: already have this event',
        );

        $this->assertSame('duplicate: already have this event', $message->getMessage());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new OkMessage(self::createEventId(), true, '');

        $result = $message->toArray();

        $this->assertSame('OK', $result[0]);
        $this->assertSame(self::VALID_EVENT_ID_HEX, $result[1]);
        $this->assertTrue($result[2]);
        $this->assertSame('', $result[3]);
        $this->assertCount(4, $result);
    }

    public function testToArrayWithRejectionAndMessage(): void
    {
        $message = new OkMessage(
            self::createEventId(),
            false,
            'blocked: you are banned',
        );

        $result = $message->toArray();

        $this->assertFalse($result[2]);
        $this->assertSame('blocked: you are banned', $result[3]);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new OkMessage(self::createEventId(), true);

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('OK', $decoded[0]);
        $this->assertSame(self::VALID_EVENT_ID_HEX, $decoded[1]);
        $this->assertTrue($decoded[2]);
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $data = ['OK', self::VALID_EVENT_ID_HEX, true, ''];

        $message = OkMessage::fromArray($data);

        $this->assertSame('OK', $message->getType());
        $this->assertSame(self::VALID_EVENT_ID_HEX, $message->getEventId()->toHex());
        $this->assertTrue($message->isAccepted());
        $this->assertSame('', $message->getMessage());
    }

    public function testFromArrayWithoutOptionalMessage(): void
    {
        $data = ['OK', self::VALID_EVENT_ID_HEX, true];

        $message = OkMessage::fromArray($data);

        $this->assertSame('', $message->getMessage());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OkMessage::fromArray(['OK', self::VALID_EVENT_ID_HEX]);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OkMessage::fromArray(['EVENT', self::VALID_EVENT_ID_HEX, true, '']);
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new OkMessage(
            self::createEventId(),
            false,
            'error: something went wrong',
        );

        $restored = OkMessage::fromArray($original->toArray());

        $this->assertSame($original->getEventId()->toHex(), $restored->getEventId()->toHex());
        $this->assertSame($original->isAccepted(), $restored->isAccepted());
        $this->assertSame($original->getMessage(), $restored->getMessage());
    }

    private static function createEventId(): EventId
    {
        return EventId::fromHex(self::VALID_EVENT_ID_HEX) ?? throw new RuntimeException('Invalid test event ID');
    }

    public function testFromArrayRejectsStringAcceptedFlag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('accepted flag must be a boolean');

        OkMessage::fromArray(['OK', self::VALID_EVENT_ID_HEX, 'true', '']);
    }

    public function testFromArrayRejectsIntegerAcceptedFlag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('accepted flag must be a boolean');

        OkMessage::fromArray(['OK', self::VALID_EVENT_ID_HEX, 1, '']);
    }

    public function testFromArrayRejectsNonStringEventId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event ID must be a string');

        OkMessage::fromArray(['OK', 42, true, '']);
    }

    public function testFromArrayRejectsNonStringReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reason must be a string');

        OkMessage::fromArray(['OK', self::VALID_EVENT_ID_HEX, true, ['array']]);
    }
}
