<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NoticeMessageTest extends TestCase
{
    public function testGetTypeReturnsNotice(): void
    {
        $message = new NoticeMessage('something happened');

        $this->assertSame('NOTICE', $message->getType());
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

    public function testFromArrayCreatesValidMessage(): void
    {
        $message = NoticeMessage::fromArray(['NOTICE', 'rate limited']);

        $this->assertSame('NOTICE', $message->getType());
        $this->assertSame('rate limited', $message->getMessage());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NoticeMessage::fromArray(['NOTICE']);
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NoticeMessage::fromArray(['AUTH', 'some message']);
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new NoticeMessage('error: could not connect');

        $restored = NoticeMessage::fromArray($original->toArray());

        $this->assertSame($original->getMessage(), $restored->getMessage());
    }

    public function testFromArrayRejectsNonStringPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('payload must be a string');

        NoticeMessage::fromArray(['NOTICE', ['structured' => 'object']]);
    }
}
