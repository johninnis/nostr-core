<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthMessageTest extends TestCase
{
    public function testGetTypeReturnsAuth(): void
    {
        $message = new AuthMessage('challenge-string-123');

        $this->assertSame('AUTH', $message->getType());
    }

    public function testGetChallengeReturnsConstructedValue(): void
    {
        $message = new AuthMessage('challenge-string-123');

        $this->assertSame('challenge-string-123', $message->getChallenge());
    }

    public function testConstructorThrowsOnEmptyChallenge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AUTH challenge cannot be empty');

        new AuthMessage('');
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new AuthMessage('challenge-abc');

        $this->assertSame(['AUTH', 'challenge-abc'], $message->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new AuthMessage('challenge-abc');

        $this->assertSame('["AUTH","challenge-abc"]', $message->toJson());
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $message = AuthMessage::fromArray(['AUTH', 'challenge-xyz']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('AUTH', $message->getType());
        $this->assertSame('challenge-xyz', $message->getChallenge());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->assertNull(AuthMessage::fromArray(['AUTH']));
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->assertNull(AuthMessage::fromArray(['NOTICE', 'challenge-xyz']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new AuthMessage('my-challenge-string');

        $restored = AuthMessage::fromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame($original->getChallenge(), $restored->getChallenge());
    }

    public function testFromArrayRejectsNonStringChallenge(): void
    {
        $this->assertNull(AuthMessage::fromArray(['AUTH', 42]));
    }
}
