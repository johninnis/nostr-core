<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NoticeMessageTest extends TestCase
{
    public function testGetTypeReturnsNotice(): void
    {
        $message = new NoticeMessage('something happened');

        $this->assertSame(RelayMessageType::Notice, $message->type());
    }

    public function testGetMessageReturnsConstructedValue(): void
    {
        $message = new NoticeMessage('rate limited');

        $this->assertSame('rate limited', $message->getMessage());
    }

    public function testConstructorThrowsOnEmptyMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Notice message cannot be empty');

        new NoticeMessage('');
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new NoticeMessage('something happened');

        $this->assertSame(['NOTICE', 'something happened'], $message->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new NoticeMessage('hello world');

        $this->assertSame('["NOTICE","hello world"]', $message->toJson());
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $message = NoticeMessage::tryFromArray(['NOTICE', 'rate limited']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Notice, $message->type());
        $this->assertSame('rate limited', $message->getMessage());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(NoticeMessage::tryFromArray(['NOTICE']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(NoticeMessage::tryFromArray(['AUTH', 'some message']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new NoticeMessage('error: could not connect');

        $restored = NoticeMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame($original->getMessage(), $restored->getMessage());
    }

    public function testTryFromArrayRejectsNonStringPayload(): void
    {
        $this->assertNull(NoticeMessage::tryFromArray(['NOTICE', ['structured' => 'object']]));
    }
}
