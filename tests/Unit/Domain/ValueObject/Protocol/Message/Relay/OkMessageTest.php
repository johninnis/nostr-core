<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OkMessageTest extends TestCase
{
    private const string VALID_EVENT_ID_HEX = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testGetTypeReturnsOk(): void
    {
        $message = OkMessage::accepted(self::createEventId());

        $this->assertSame(RelayMessageType::Ok, $message->type());
    }

    public function testGetEventIdReturnsConstructedValue(): void
    {
        $eventId = self::createEventId();
        $message = OkMessage::accepted($eventId);

        $this->assertTrue($eventId->equals($message->getEventId()));
    }

    public function testIsAcceptedReturnsTrueWhenAccepted(): void
    {
        $message = OkMessage::accepted(self::createEventId());

        $this->assertTrue($message->isAccepted());
    }

    public function testIsAcceptedReturnsFalseWhenRejected(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::Blocked, 'spam');

        $this->assertFalse($message->isAccepted());
    }

    public function testGetMessageReturnsEmptyStringByDefault(): void
    {
        $message = OkMessage::accepted(self::createEventId());

        $this->assertSame('', $message->getMessage());
    }

    public function testGetMessageReturnsConstructedValue(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::Duplicate, 'already have this event');

        $this->assertSame('duplicate: already have this event', $message->getMessage());
    }

    public function testIsAuthRequiredWhenRejectedWithAuthRequiredPrefix(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::AuthRequired, 'please authenticate');

        $this->assertTrue($message->isAuthRequired());
    }

    public function testIsAuthRequiredIsFalseWhenAccepted(): void
    {
        $message = OkMessage::accepted(self::createEventId(), 'auth-required: please authenticate');

        $this->assertFalse($message->isAuthRequired());
    }

    public function testIsAuthRequiredIsFalseWhenRejectedWithoutPrefix(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::Blocked, 'spam');

        $this->assertFalse($message->isAuthRequired());
    }

    public function testGetReasonPrefixReadsTheMachineReadablePrefix(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::RateLimited, 'slow down');

        $this->assertSame(ReasonPrefix::RateLimited, $message->getReasonPrefix());
    }

    public function testGetReasonPrefixIsReadOnAnAcceptedMessageToo(): void
    {
        $message = OkMessage::accepted(self::createEventId(), 'duplicate: already have this event');

        $this->assertSame(ReasonPrefix::Duplicate, $message->getReasonPrefix());
    }

    public function testGetReasonPrefixIsNullForAnAcceptanceWithoutAPrefix(): void
    {
        $message = OkMessage::accepted(self::createEventId(), 'something went fine');

        $this->assertNull($message->getReasonPrefix());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = OkMessage::accepted(self::createEventId(), '');

        $result = $message->toArray();

        $this->assertSame('OK', $result[0]);
        $this->assertSame(self::VALID_EVENT_ID_HEX, $result[1]);
        $this->assertTrue($result[2]);
        $this->assertSame('', $result[3]);
        $this->assertCount(4, $result);
    }

    public function testToArrayWithRejectionAndMessage(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::Blocked, 'you are banned');

        $result = $message->toArray();

        $this->assertFalse($result[2]);
        $this->assertSame('blocked: you are banned', $result[3]);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = OkMessage::accepted(self::createEventId());

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('OK', $decoded[0]);
        $this->assertSame(self::VALID_EVENT_ID_HEX, $decoded[1]);
        $this->assertTrue($decoded[2]);
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $data = ['OK', self::VALID_EVENT_ID_HEX, true, ''];

        $message = OkMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Ok, $message->type());
        $this->assertSame(self::VALID_EVENT_ID_HEX, $message->getEventId()->toHex());
        $this->assertTrue($message->isAccepted());
        $this->assertSame('', $message->getMessage());
    }

    public function testTryFromArrayReturnsNullForAnAcceptedOkWithoutItsMessage(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, true]));
    }

    public function testTryFromArrayReturnsNullForARejectedOkWithoutItsMessage(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, false]));
    }

    public function testTryFromJsonReturnsNullForAnOkWithoutItsMessage(): void
    {
        $this->assertNull(OkMessage::tryFromJson('["OK","'.self::VALID_EVENT_ID_HEX.'",true]'));
    }

    public function testTryFromJsonReadsTheSpecExampleWithAnEmptyMessage(): void
    {
        $message = OkMessage::tryFromJson('["OK","'.self::VALID_EVENT_ID_HEX.'",true,""]')
            ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('', $message->getMessage());
    }

    public function testTryFromArrayIgnoresATrailingElement(): void
    {
        $message = OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, false, 'blocked: spam', 'extra']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('blocked: spam', $message->getMessage());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX]));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['EVENT', self::VALID_EVENT_ID_HEX, true, '']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = OkMessage::refused(self::createEventId(), ReasonPrefix::Error, 'something went wrong');

        $restored = OkMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame($original->getEventId()->toHex(), $restored->getEventId()->toHex());
        $this->assertSame($original->isAccepted(), $restored->isAccepted());
        $this->assertSame($original->getMessage(), $restored->getMessage());
    }

    public function testTryFromJsonParsesAKnownMessageType(): void
    {
        $message = OkMessage::tryFromJson('["OK","'.self::VALID_EVENT_ID_HEX.'",false,"blocked: spam"]')
            ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(self::VALID_EVENT_ID_HEX, $message->getEventId()->toHex());
        $this->assertFalse($message->isAccepted());
        $this->assertSame('blocked: spam', $message->getMessage());
    }

    public function testTryFromJsonReturnsNullOnMalformedJson(): void
    {
        $this->assertNull(OkMessage::tryFromJson('not json'));
    }

    public function testTryFromJsonReturnsNullOnJsonObject(): void
    {
        $this->assertNull(OkMessage::tryFromJson('{"0":"OK","2":true}'));
    }

    private static function createEventId(): EventId
    {
        return EventId::tryFromHex(self::VALID_EVENT_ID_HEX) ?? throw new RuntimeException('Invalid test event ID');
    }

    public function testTryFromArrayRejectsStringAcceptedFlag(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, 'true', '']));
    }

    public function testTryFromArrayRejectsIntegerAcceptedFlag(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, 1, '']));
    }

    public function testTryFromArrayRejectsNonStringEventId(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', 42, true, '']));
    }

    public function testTryFromArrayRejectsNonStringReason(): void
    {
        $this->assertNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, true, ['array']]));
    }

    public function testTryFromArrayReadsEveryNip01RejectionExample(): void
    {
        foreach ([
            'blocked: you are banned from posting here',
            'blocked: please register your pubkey at https://my-expensive-relay.example.com',
            'rate-limited: slow down there chief',
            'invalid: event creation date is too far off from the current time',
            'pow: difficulty 26 is less than 30',
            'restricted: not allowed to write.',
            'error: could not connect to the database',
            "mute: no one was listening to your ephemeral event and it wasn't handled in any way, it was ignored",
        ] as $reason) {
            $this->assertNotNull(OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, false, $reason]), $reason);
        }
    }

    public function testARefusalWithoutAPrefixIsReadAsAnError(): void
    {
        $message = OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, false, 'something went wrong']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ReasonPrefix::Error, $message->getReasonPrefix());
        $this->assertSame('something went wrong', $message->getMessage());
    }

    public function testARefusalWithAnEmptyMessageIsReadAsAnError(): void
    {
        $message = OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, false, '']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ReasonPrefix::Error, $message->getReasonPrefix());
    }

    public function testARefusalWithAPrefixOutsideTheProtocolListIsReadAsAnError(): void
    {
        $message = OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, false, 'teapot: short and stout']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ReasonPrefix::Error, $message->getReasonPrefix());
    }

    public function testAnAcceptanceWithoutAPrefixHasNoReason(): void
    {
        $message = OkMessage::tryFromArray(['OK', self::VALID_EVENT_ID_HEX, true, 'stored']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertNull($message->getReasonPrefix());
    }

    public function testRefusedWritesThePrefixBeforeTheDetail(): void
    {
        $message = OkMessage::refused(self::createEventId(), ReasonPrefix::Blocked, 'spam');

        $this->assertSame('blocked: spam', $message->getMessage());
    }

    public function testRefusedIsNotAccepted(): void
    {
        $this->assertFalse(OkMessage::refused(self::createEventId(), ReasonPrefix::Blocked, 'spam')->isAccepted());
    }

    public function testAcceptedIsAccepted(): void
    {
        $this->assertTrue(OkMessage::accepted(self::createEventId())->isAccepted());
    }
}
