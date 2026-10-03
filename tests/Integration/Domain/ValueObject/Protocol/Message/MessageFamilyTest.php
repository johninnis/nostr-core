<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;

final class MessageFamilyTest extends TestCase
{
    private KeyPair $keyPair;
    private Event $event;

    protected function setUp(): void
    {
        $this->keyPair = KeyPair::generate(CryptoFixtures::signer());

        $rumour = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr!'),
            new TagCollection(),
            Timestamp::now(),
        );
        $this->event = $rumour->sign($this->keyPair, CryptoFixtures::signer());
    }

    public function testTryFromJsonParsesClientEventMessage(): void
    {
        $eventData = $this->event->toArray();
        $json = json_encode(['EVENT', $eventData]);
        $this->assertNotFalse($json);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(ClientEventMessage::class, $message);
        $this->assertSame(ClientMessageType::Event, $message->type());
        $this->assertTrue($message->getEvent()->getId()->equals($this->event->getId()));
    }

    public function testTryFromJsonParsesClientCloseMessage(): void
    {
        $json = json_encode(['CLOSE', 'test-sub']);
        $this->assertNotFalse($json);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(CloseMessage::class, $message);
        $this->assertSame(ClientMessageType::Close, $message->type());
        $this->assertSame('test-sub', (string) $message->getSubscriptionId());
    }

    public function testTryFromJsonParsesRelayOkMessage(): void
    {
        $eventId = str_repeat('a', 64);
        $json = json_encode(['OK', $eventId, true, 'accepted']);
        $this->assertNotFalse($json);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertSame(RelayMessageType::Ok, $message->type());
        $this->assertSame($eventId, $message->getEventId()->toHex());
        $this->assertTrue($message->isAccepted());
        $this->assertSame('accepted', $message->getMessage());
    }

    public function testTryFromJsonParsesRelayNoticeMessage(): void
    {
        $json = json_encode(['NOTICE', 'Test notice']);
        $this->assertNotFalse($json);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(NoticeMessage::class, $message);
        $this->assertSame(RelayMessageType::Notice, $message->type());
        $this->assertSame('Test notice', $message->getMessage());
    }

    public function testTryFromJsonParsesRelayClosedMessage(): void
    {
        $json = json_encode(['CLOSED', 'test-sub', 'error: subscription ended']);
        $this->assertNotFalse($json);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(ClosedMessage::class, $message);
        $this->assertSame(RelayMessageType::Closed, $message->type());
        $this->assertSame('test-sub', (string) $message->getSubscriptionId());
        $this->assertSame('error: subscription ended', $message->getMessage());
    }

    public function testRefusesARelayClosedMessageWithoutReason(): void
    {
        $json = json_encode(['CLOSED', 'test-sub']);
        $this->assertNotFalse($json);

        $this->assertNull(RelayMessage::tryFromJson($json));
    }

    public function testReturnsNullForInvalidClientMessageJson(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('invalid json'));
    }

    public function testReturnsNullForInvalidRelayMessageJson(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('invalid json'));
    }

    public function testReturnsNullForUnknownClientMessageType(): void
    {
        $json = json_encode(['UNKNOWN', 'data']);
        $this->assertNotFalse($json);

        $this->assertNull(ClientMessage::tryFromJson($json));
    }

    public function testReturnsNullForUnknownRelayMessageType(): void
    {
        $json = json_encode(['UNKNOWN', 'data']);
        $this->assertNotFalse($json);

        $this->assertNull(RelayMessage::tryFromJson($json));
    }

    public function testReturnsNullForEmptyClientMessage(): void
    {
        $json = json_encode([]);
        $this->assertNotFalse($json);

        $this->assertNull(ClientMessage::tryFromJson($json));
    }

    public function testReturnsNullForEmptyRelayMessage(): void
    {
        $json = json_encode([]);
        $this->assertNotFalse($json);

        $this->assertNull(RelayMessage::tryFromJson($json));
    }

    public function testRoundTripPreservesData(): void
    {
        $originalMessage = new ClientEventMessage($this->event);
        $json = $originalMessage->toJson();
        $deserialisedMessage = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(ClientEventMessage::class, $deserialisedMessage);
        $this->assertSame($originalMessage->type(), $deserialisedMessage->type());
        $this->assertTrue(
            $originalMessage->getEvent()->getId()->equals($deserialisedMessage->getEvent()->getId())
        );
    }
}
