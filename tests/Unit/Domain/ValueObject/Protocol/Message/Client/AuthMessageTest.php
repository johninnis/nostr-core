<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\AuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthMessageTest extends TestCase
{
    public function testGetTypeReturnsAuth(): void
    {
        $message = new AuthMessage($this->createAuthEvent());

        $this->assertSame(ClientMessageType::Auth, $message->type());
    }

    public function testGetEventReturnsConstructedEvent(): void
    {
        $event = $this->createAuthEvent();
        $message = new AuthMessage($event);

        $this->assertSame($event, $message->getEvent());
    }

    public function testConstructorThrowsOnNonAuthKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AUTH message must contain a kind 22242 event');

        $event = EventMother::fromRumour(new Rumour(
            self::createPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString(''),
        ));

        new AuthMessage($event);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $event = $this->createAuthEvent();
        $message = new AuthMessage($event);

        $result = $message->toArray();

        $this->assertSame('AUTH', $result[0]);
        $this->assertSame($event->toArray(), $result[1]);
        $this->assertCount(2, $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new AuthMessage($this->createAuthEvent());

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('AUTH', $decoded[0]);
        $this->assertIsArray($decoded[1]);
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $event = $this->createAuthEvent();
        $data = ['AUTH', $event->toArray()];

        $message = AuthMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ClientMessageType::Auth, $message->type());
        $this->assertSame(EventKind::CLIENT_AUTH, $message->getEvent()->getKind()->toInt());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(AuthMessage::tryFromArray(['AUTH']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(AuthMessage::tryFromArray(['EVENT', $this->createAuthEvent()->toArray()]));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new AuthMessage($this->createAuthEvent());

        $restored = AuthMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            $original->getEvent()->getPubkey()->toHex(),
            $restored->getEvent()->getPubkey()->toHex()
        );
        $this->assertSame(
            $original->getEvent()->getKind()->toInt(),
            $restored->getEvent()->getKind()->toInt()
        );
    }

    private static function createPublicKey(): PublicKey
    {
        return PublicKey::tryFromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid test public key');
    }

    private function createAuthEvent(): Event
    {
        return EventMother::fromRumour(new Rumour(
            self::createPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::CLIENT_AUTH),
            new TagCollection(),
            EventContent::fromString(''),
        ));
    }
}
