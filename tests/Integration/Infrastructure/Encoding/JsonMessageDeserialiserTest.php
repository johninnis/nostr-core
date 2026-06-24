<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Encoding;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Encoding\JsonMessageDeserialiser;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;

final class JsonMessageDeserialiserTest extends TestCase
{
    private JsonMessageDeserialiser $deserialiser;
    private KeyPair $keyPair;
    private Event $event;

    protected function setUp(): void
    {
        $this->deserialiser = new JsonMessageDeserialiser();
        $this->keyPair = KeyPair::generate(CryptoFixtures::signer());

        $this->event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr!')
        );
        $this->event = $this->event->sign($this->keyPair, CryptoFixtures::signer());
    }

    public function testCanDeserialiseClientEventMessage(): void
    {
        $eventData = $this->event->toArray();
        $json = json_encode(['EVENT', $eventData]);
        $this->assertNotFalse($json);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ClientEventMessage::class, $message);
        $this->assertSame('EVENT', $message->getType());
        $this->assertTrue($message->getEvent()->getId()->equals($this->event->getId()));
    }

    public function testCanDeserialiseClientCloseMessage(): void
    {
        $json = json_encode(['CLOSE', 'test-sub']);
        $this->assertNotFalse($json);

        $message = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(CloseMessage::class, $message);
        $this->assertSame('CLOSE', $message->getType());
        $this->assertSame('test-sub', (string) $message->getSubscriptionId());
    }

    public function testCanDeserialiseRelayOkMessage(): void
    {
        $eventId = str_repeat('a', 64);
        $json = json_encode(['OK', $eventId, true, 'accepted']);
        $this->assertNotFalse($json);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertSame('OK', $message->getType());
        $this->assertSame($eventId, $message->getEventId()->toHex());
        $this->assertTrue($message->isAccepted());
        $this->assertSame('accepted', $message->getMessage());
    }

    public function testCanDeserialiseRelayNoticeMessage(): void
    {
        $json = json_encode(['NOTICE', 'Test notice']);
        $this->assertNotFalse($json);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(NoticeMessage::class, $message);
        $this->assertSame('NOTICE', $message->getType());
        $this->assertSame('Test notice', $message->getMessage());
    }

    public function testCanDeserialiseRelayClosedMessage(): void
    {
        $json = json_encode(['CLOSED', 'test-sub', 'subscription ended']);
        $this->assertNotFalse($json);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(ClosedMessage::class, $message);
        $this->assertSame('CLOSED', $message->getType());
        $this->assertSame('test-sub', (string) $message->getSubscriptionId());
        $this->assertSame('subscription ended', $message->getMessage());
    }

    public function testCanDeserialiseRelayClosedMessageWithoutReason(): void
    {
        $json = json_encode(['CLOSED', 'test-sub']);
        $this->assertNotFalse($json);

        $message = $this->deserialiser->deserialiseRelayMessage($json);

        $this->assertInstanceOf(ClosedMessage::class, $message);
        $this->assertSame('', $message->getMessage());
    }

    public function testReturnsNullForInvalidClientMessageJson(): void
    {
        $this->assertNull($this->deserialiser->deserialiseClientMessage('invalid json'));
    }

    public function testReturnsNullForInvalidRelayMessageJson(): void
    {
        $this->assertNull($this->deserialiser->deserialiseRelayMessage('invalid json'));
    }

    public function testReturnsNullForUnknownClientMessageType(): void
    {
        $json = json_encode(['UNKNOWN', 'data']);
        $this->assertNotFalse($json);

        $this->assertNull($this->deserialiser->deserialiseClientMessage($json));
    }

    public function testReturnsNullForUnknownRelayMessageType(): void
    {
        $json = json_encode(['UNKNOWN', 'data']);
        $this->assertNotFalse($json);

        $this->assertNull($this->deserialiser->deserialiseRelayMessage($json));
    }

    public function testReturnsNullForEmptyClientMessage(): void
    {
        $json = json_encode([]);
        $this->assertNotFalse($json);

        $this->assertNull($this->deserialiser->deserialiseClientMessage($json));
    }

    public function testReturnsNullForEmptyRelayMessage(): void
    {
        $json = json_encode([]);
        $this->assertNotFalse($json);

        $this->assertNull($this->deserialiser->deserialiseRelayMessage($json));
    }

    public function testDeserialisationRoundTripPreservesData(): void
    {
        $originalMessage = new ClientEventMessage($this->event);
        $json = $originalMessage->toJson();
        $deserialisedMessage = $this->deserialiser->deserialiseClientMessage($json);

        $this->assertInstanceOf(ClientEventMessage::class, $deserialisedMessage);
        $this->assertSame($originalMessage->getType(), $deserialisedMessage->getType());
        $this->assertTrue(
            $originalMessage->getEvent()->getId()->equals($deserialisedMessage->getEvent()->getId())
        );
    }
}
