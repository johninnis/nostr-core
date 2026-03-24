<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Adapter;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\AuthMessage as ClientAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage as RelayAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage as RelayEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Adapter\JsonMessageSerialiserAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonMessageSerialiserAdapterTest extends TestCase
{
    private JsonMessageSerialiserAdapter $serialiser;

    protected function setUp(): void
    {
        $this->serialiser = new JsonMessageSerialiserAdapter();
    }

    public function testDeserialiseRelayEventMessage(): void
    {
        $event = $this->createEvent();
        $json = json_encode(['EVENT', 'sub-1', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(RelayEventMessage::class, $message);
        $this->assertSame('EVENT', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseRelayOkMessageAccepted(): void
    {
        $eventId = str_repeat('aa', 32);
        $json = json_encode(['OK', $eventId, true, ''], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertSame('OK', $message->getType());
        $this->assertTrue($message->isAccepted());
    }

    public function testDeserialiseRelayOkMessageRejected(): void
    {
        $eventId = str_repeat('aa', 32);
        $json = json_encode(['OK', $eventId, false, 'duplicate: already have this event'], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertFalse($message->isAccepted());
        $this->assertSame('duplicate: already have this event', $message->getMessage());
    }

    public function testDeserialiseRelayEoseMessage(): void
    {
        $json = json_encode(['EOSE', 'sub-1'], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(EoseMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseRelayClosedMessage(): void
    {
        $json = json_encode(['CLOSED', 'sub-1', 'error: shutting down'], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(ClosedMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame('error: shutting down', $message->getMessage());
    }

    public function testDeserialiseRelayNoticeMessage(): void
    {
        $json = json_encode(['NOTICE', 'rate limited'], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(NoticeMessage::class, $message);
        $this->assertSame('rate limited', $message->getMessage());
    }

    public function testDeserialiseRelayAuthMessage(): void
    {
        $json = json_encode(['AUTH', 'challenge-string-123'], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(RelayAuthMessage::class, $message);
        $this->assertSame('challenge-string-123', $message->getChallenge());
    }

    public function testDeserialiseRelayMessageThrowsOnInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serialiser->deserialiseRelayMessage('not valid json');
    }

    public function testDeserialiseRelayMessageThrowsOnEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serialiser->deserialiseRelayMessage('[]');
    }

    public function testDeserialiseRelayMessageThrowsOnUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown relay message type');

        $this->serialiser->deserialiseRelayMessage('["UNKNOWN","data"]');
    }

    public function testDeserialiseClientEventMessage(): void
    {
        $event = $this->createEvent();
        $json = json_encode(['EVENT', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ClientEventMessage::class, $message);
        $this->assertSame('EVENT', $message->getType());
    }

    public function testDeserialiseClientReqMessage(): void
    {
        $json = json_encode(['REQ', 'sub-1', ['kinds' => [1]]], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ReqMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseClientCloseMessage(): void
    {
        $json = json_encode(['CLOSE', 'sub-1'], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(CloseMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseClientAuthMessage(): void
    {
        $event = $this->createAuthEvent();
        $json = json_encode(['AUTH', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ClientAuthMessage::class, $message);
        $this->assertSame(EventKind::CLIENT_AUTH, $message->getEvent()->getKind()->toInt());
    }

    public function testDeserialiseClientCountMessage(): void
    {
        $json = json_encode(['COUNT', 'sub-1', ['kinds' => [1]]], JSON_THROW_ON_ERROR);

        $message = $this->serialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(CountMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseClientMessageThrowsOnInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serialiser->deserialiseClientMessage('not valid json');
    }

    public function testDeserialiseClientMessageThrowsOnEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serialiser->deserialiseClientMessage('[]');
    }

    public function testDeserialiseClientMessageThrowsOnUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown client message type');

        $this->serialiser->deserialiseClientMessage('["UNKNOWN","data"]');
    }

    public function testDeserialiseClientMessageThrowsOnNonArrayJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serialiser->deserialiseClientMessage('"just a string"');
    }

    public function testDeserialiseRelayMessageThrowsOnNonArrayJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serialiser->deserialiseRelayMessage('"just a string"');
    }

    private static function createPublicKey(): PublicKey
    {
        return PublicKey::fromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid test public key');
    }

    private function createEvent(): Event
    {
        return new Event(
            self::createPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test content'),
        );
    }

    private function createAuthEvent(): Event
    {
        return new Event(
            self::createPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::clientAuth(),
            TagCollection::empty(),
            EventContent::fromString(''),
        );
    }
}
