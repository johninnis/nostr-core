<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthMessageTest extends TestCase
{
    public function testGetTypeReturnsAuth(): void
    {
        $message = new AuthMessage(Challenge::fromString('challenge-string-123'));

        $this->assertSame(RelayMessageType::Auth, $message->type());
    }

    public function testGetChallengeReturnsConstructedValue(): void
    {
        $message = new AuthMessage(Challenge::fromString('challenge-string-123'));

        $this->assertSame('challenge-string-123', (string) $message->getChallenge());
    }

    public function testTryFromArrayReturnsNullOnEmptyChallenge(): void
    {
        $this->assertNull(AuthMessage::tryFromArray(['AUTH', '']));
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new AuthMessage(Challenge::fromString('challenge-abc'));

        $this->assertSame(['AUTH', 'challenge-abc'], $message->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new AuthMessage(Challenge::fromString('challenge-abc'));

        $this->assertSame('["AUTH","challenge-abc"]', $message->toJson());
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $message = AuthMessage::tryFromArray(['AUTH', 'challenge-xyz']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Auth, $message->type());
        $this->assertSame('challenge-xyz', (string) $message->getChallenge());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(AuthMessage::tryFromArray(['AUTH']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(AuthMessage::tryFromArray(['NOTICE', 'challenge-xyz']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new AuthMessage(Challenge::fromString('my-challenge-string'));

        $restored = AuthMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertTrue($original->getChallenge()->equals($restored->getChallenge()));
    }

    public function testTryFromArrayRejectsNonStringChallenge(): void
    {
        $this->assertNull(AuthMessage::tryFromArray(['AUTH', 42]));
    }
}
