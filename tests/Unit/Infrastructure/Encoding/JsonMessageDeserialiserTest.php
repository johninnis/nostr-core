<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Encoding;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
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
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\CountMessage as RelayCountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage as RelayEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Encoding\JsonMessageDeserialiser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonMessageDeserialiserTest extends TestCase
{
    private JsonMessageDeserialiser $deserialiser;

    protected function setUp(): void
    {
        $this->deserialiser = new JsonMessageDeserialiser();
    }

    public function testDeserialiseRelayEventMessage(): void
    {
        $event = $this->createEvent();
        $json = json_encode(['EVENT', 'sub-1', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(RelayEventMessage::class, $message);
        $this->assertSame('EVENT', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseRelayOkMessageAccepted(): void
    {
        $eventId = str_repeat('aa', 32);
        $json = json_encode(['OK', $eventId, true, ''], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertSame('OK', $message->getType());
        $this->assertTrue($message->isAccepted());
    }

    public function testDeserialiseRelayOkMessageRejected(): void
    {
        $eventId = str_repeat('aa', 32);
        $json = json_encode(['OK', $eventId, false, 'duplicate: already have this event'], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertFalse($message->isAccepted());
        $this->assertSame('duplicate: already have this event', $message->getMessage());
    }

    public function testDeserialiseRelayEoseMessage(): void
    {
        $json = json_encode(['EOSE', 'sub-1'], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(EoseMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseRelayClosedMessage(): void
    {
        $json = json_encode(['CLOSED', 'sub-1', 'error: shutting down'], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(ClosedMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame('error: shutting down', $message->getMessage());
    }

    public function testDeserialiseRelayNoticeMessage(): void
    {
        $json = json_encode(['NOTICE', 'rate limited'], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(NoticeMessage::class, $message);
        $this->assertSame('rate limited', $message->getMessage());
    }

    public function testDeserialiseRelayAuthMessage(): void
    {
        $json = json_encode(['AUTH', 'challenge-string-123'], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(RelayAuthMessage::class, $message);
        $this->assertSame('challenge-string-123', $message->getChallenge());
    }

    public function testDeserialiseRelayCountMessage(): void
    {
        $json = json_encode(['COUNT', 'sub-1', ['count' => 42]], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(RelayCountMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame(42, $message->getCount());
    }

    public function testDeserialiseRelayMessageReturnsNullOnInvalidJson(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('not valid json'));
    }

    public function testDeserialiseRelayMessageReturnsNullOnEmptyArray(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('[]'));
    }

    public function testDeserialiseRelayMessageReturnsNullOnUnknownType(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('["UNKNOWN","data"]'));
    }

    public function testDeserialiseClientEventMessage(): void
    {
        $event = $this->createEvent();
        $json = json_encode(['EVENT', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ClientEventMessage::class, $message);
        $this->assertSame('EVENT', $message->getType());
    }

    public function testDeserialiseClientReqMessage(): void
    {
        $json = json_encode(['REQ', 'sub-1', ['kinds' => [1]]], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ReqMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseClientCloseMessage(): void
    {
        $json = json_encode(['CLOSE', 'sub-1'], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(CloseMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseClientAuthMessage(): void
    {
        $event = $this->createAuthEvent();
        $json = json_encode(['AUTH', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ClientAuthMessage::class, $message);
        $this->assertSame(EventKind::CLIENT_AUTH, $message->getEvent()->getKind()->toInt());
    }

    public function testDeserialiseClientCountMessage(): void
    {
        $json = json_encode(['COUNT', 'sub-1', ['kinds' => [1]]], JSON_THROW_ON_ERROR);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(CountMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testDeserialiseClientMessageReturnsNullOnInvalidJson(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('not valid json'));
    }

    public function testDeserialiseClientMessageReturnsNullOnEmptyArray(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('[]'));
    }

    public function testDeserialiseClientMessageReturnsNullOnUnknownType(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('["UNKNOWN","data"]'));
    }

    public function testDeserialiseClientMessageReturnsNullOnNonArrayJson(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('"just a string"'));
    }

    public function testDeserialiseRelayMessageReturnsNullOnNonArrayJson(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('"just a string"'));
    }

    public function testDeserialiseClientEventMessageReturnsNullOnNonArrayEventPayload(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('["EVENT","not-an-event-object"]'));
    }

    public function testDeserialiseClientAuthMessageReturnsNullOnNonArrayEventPayload(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('["AUTH","not-an-event-object"]'));
    }

    public function testDeserialiseRelayEventMessageReturnsNullOnNonArrayEventPayload(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('["EVENT","sub-1","not-an-event-object"]'));
    }

    public function testDeserialiseClientMessageReturnsNullOnJsonObject(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('{"type":"EVENT"}'));
    }

    public function testDeserialiseRelayMessageReturnsNullOnJsonObject(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('{"type":"OK"}'));
    }

    public function testDeserialiseClientMessageReturnsNullOnSparseNumericKeyObject(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('{"0":"EVENT","2":{}}'));
    }

    public function testDeserialiseRelayMessageReturnsNullOnSparseNumericKeyObject(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('{"0":"OK","3":true}'));
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
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('test content'),
        );
    }

    private function createAuthEvent(): Event
    {
        return new Event(
            self::createPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::CLIENT_AUTH),
            new TagCollection(),
            EventContent::fromString(''),
        );
    }
}
