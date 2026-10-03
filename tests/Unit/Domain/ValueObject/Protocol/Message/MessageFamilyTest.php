<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\AuthMessage as ClientAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\FilterRequestMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage as RelayAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\CountMessage as RelayCountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage as RelayEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageFamilyTest extends TestCase
{
    public function testTryFromJsonParsesRelayEventMessage(): void
    {
        $event = $this->createEvent();
        $json = json_encode(['EVENT', 'sub-1', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(RelayEventMessage::class, $message);
        $this->assertSame(RelayMessageType::Event, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testTryFromJsonParsesRelayOkMessageAccepted(): void
    {
        $eventId = str_repeat('aa', 32);
        $json = json_encode(['OK', $eventId, true, ''], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertSame(RelayMessageType::Ok, $message->type());
        $this->assertTrue($message->isAccepted());
    }

    public function testTryFromJsonParsesRelayOkMessageRejected(): void
    {
        $eventId = str_repeat('aa', 32);
        $json = json_encode(['OK', $eventId, false, 'duplicate: already have this event'], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(OkMessage::class, $message);
        $this->assertFalse($message->isAccepted());
        $this->assertSame('duplicate: already have this event', $message->getMessage());
    }

    public function testTryFromJsonParsesRelayEoseMessage(): void
    {
        $json = json_encode(['EOSE', 'sub-1'], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(EoseMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testTryFromJsonParsesRelayClosedMessage(): void
    {
        $json = json_encode(['CLOSED', 'sub-1', 'error: shutting down'], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(ClosedMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame('error: shutting down', $message->getMessage());
    }

    public function testTryFromJsonParsesRelayNoticeMessage(): void
    {
        $json = json_encode(['NOTICE', 'rate limited'], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(NoticeMessage::class, $message);
        $this->assertSame('rate limited', $message->getMessage());
    }

    public function testTryFromJsonParsesRelayAuthMessage(): void
    {
        $json = json_encode(['AUTH', 'challenge-string-123'], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(RelayAuthMessage::class, $message);
        $this->assertSame('challenge-string-123', (string) $message->getChallenge());
    }

    public function testTryFromJsonParsesRelayCountMessage(): void
    {
        $json = json_encode(['COUNT', 'sub-1', ['count' => 42]], JSON_THROW_ON_ERROR);

        $message = RelayMessage::tryFromJson($json);

        $this->assertInstanceOf(RelayCountMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertSame(42, $message->getCount()->toInt());
    }

    public function testRelayMessageTryFromJsonReturnsNullOnInvalidJson(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('not valid json'));
    }

    public function testRelayMessageTryFromJsonReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('[]'));
    }

    public function testRelayMessageTryFromJsonReturnsNullOnUnknownType(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('["UNKNOWN","data"]'));
    }

    public function testTryFromJsonParsesClientEventMessage(): void
    {
        $event = $this->createEvent();
        $json = json_encode(['EVENT', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(ClientEventMessage::class, $message);
        $this->assertSame(ClientMessageType::Event, $message->type());
    }

    public function testTryFromJsonParsesClientReqMessage(): void
    {
        $json = json_encode(['REQ', 'sub-1', ['kinds' => [1]]], JSON_THROW_ON_ERROR);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(ReqMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testTryFromJsonParsesClientCloseMessage(): void
    {
        $json = json_encode(['CLOSE', 'sub-1'], JSON_THROW_ON_ERROR);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(CloseMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testTryFromJsonParsesClientAuthMessage(): void
    {
        $event = $this->createAuthEvent();
        $json = json_encode(['AUTH', $event->toArray()], JSON_THROW_ON_ERROR);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(ClientAuthMessage::class, $message);
        $this->assertSame(EventKind::CLIENT_AUTH, $message->getEvent()->getKind()->toInt());
    }

    public function testTryFromJsonParsesClientCountMessage(): void
    {
        $json = json_encode(['COUNT', 'sub-1', ['kinds' => [1]]], JSON_THROW_ON_ERROR);

        $message = ClientMessage::tryFromJson($json);

        $this->assertInstanceOf(CountMessage::class, $message);
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testClientMessageTryFromJsonReturnsNullOnInvalidJson(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('not valid json'));
    }

    public function testClientMessageTryFromJsonReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('[]'));
    }

    public function testClientMessageTryFromJsonReturnsNullOnUnknownType(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('["UNKNOWN","data"]'));
    }

    public function testClientMessageTryFromJsonReturnsNullOnNonArrayJson(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('"just a string"'));
    }

    public function testRelayMessageTryFromJsonReturnsNullOnNonArrayJson(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('"just a string"'));
    }

    public function testClientEventMessageTryFromJsonReturnsNullOnNonArrayEventPayload(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('["EVENT","not-an-event-object"]'));
    }

    public function testClientAuthMessageTryFromJsonReturnsNullOnNonArrayEventPayload(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('["AUTH","not-an-event-object"]'));
    }

    public function testRelayEventMessageTryFromJsonReturnsNullOnNonArrayEventPayload(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('["EVENT","sub-1","not-an-event-object"]'));
    }

    public function testClientMessageTryFromJsonReturnsNullOnJsonObject(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('{"type":"EVENT"}'));
    }

    public function testRelayMessageTryFromJsonReturnsNullOnJsonObject(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('{"type":"OK"}'));
    }

    public function testClientMessageTryFromJsonReturnsNullOnSparseNumericKeyObject(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('{"0":"EVENT","2":{}}'));
    }

    public function testRelayMessageTryFromJsonReturnsNullOnSparseNumericKeyObject(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('{"0":"OK","3":true}'));
    }

    private static function createPublicKey(): PublicKey
    {
        return PublicKey::tryFromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid test public key');
    }

    private function createEvent(): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            self::createPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test content'),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        ));
    }

    private function createAuthEvent(): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            self::createPublicKey(),
            EventKind::fromInt(EventKind::CLIENT_AUTH),
            EventContent::fromString(''),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        ));
    }

    public function testClientMessageTryFromJsonRefusesAReqWhoseFilterIsAnEmptyList(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('["REQ","s",[]]'));
    }

    public function testClientMessageTryFromJsonRefusesAReqWhoseFilterStatesAFieldAsNull(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('["REQ","s",{"authors":null}]'));
    }

    public function testRelayMessageTryFromJsonRefusesAnObjectKeyedLikeAMessage(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('{"0":"EOSE","1":"s"}'));
    }

    public function testRelayMessageTryFromJsonReadsAnEoseCarryingACompletenessHint(): void
    {
        $this->assertInstanceOf(EoseMessage::class, RelayMessage::tryFromJson('["EOSE","s",["finish"]]'));
    }

    public function testClientMessageTryFromArrayDispatchesByTag(): void
    {
        $this->assertInstanceOf(CloseMessage::class, ClientMessage::tryFromArray(['CLOSE', 'sub-1']));
    }

    public function testRelayMessageTryFromArrayDispatchesByTag(): void
    {
        $this->assertInstanceOf(EoseMessage::class, RelayMessage::tryFromArray(['EOSE', 'sub-1']));
    }

    public function testClientMessageTryFromJsonRefusesARelayOnlyTag(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('["EOSE","sub-1"]'));
    }

    public function testRelayMessageTryFromJsonRefusesAClientOnlyTag(): void
    {
        $this->assertNull(RelayMessage::tryFromJson('["CLOSE","sub-1"]'));
    }

    public function testClientMessageTryFromJsonRefusesANonStringTag(): void
    {
        $this->assertNull(ClientMessage::tryFromJson('[1,"sub-1"]'));
    }

    public function testFilterRequestMessageTryFromJsonParsesAReq(): void
    {
        $this->assertInstanceOf(ReqMessage::class, FilterRequestMessage::tryFromJson('["REQ","sub-1",{"kinds":[1]}]'));
    }

    public function testFilterRequestMessageTryFromJsonParsesACount(): void
    {
        $this->assertInstanceOf(CountMessage::class, FilterRequestMessage::tryFromJson('["COUNT","sub-1",{"kinds":[1]}]'));
    }

    public function testFilterRequestMessageTryFromJsonRefusesAClose(): void
    {
        $this->assertNull(FilterRequestMessage::tryFromJson('["CLOSE","sub-1"]'));
    }

    public function testLeafTryFromJsonRefusesASiblingLeafsTag(): void
    {
        $this->assertNull(ReqMessage::tryFromJson('["COUNT","sub-1",{"kinds":[1]}]'));
    }

    public function testLeafTryFromJsonParsesItsOwnTag(): void
    {
        $this->assertInstanceOf(OkMessage::class, OkMessage::tryFromJson('["OK","'.str_repeat('aa', 32).'",true,""]'));
    }
}
