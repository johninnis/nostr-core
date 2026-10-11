<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\EoseHint;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EoseMessageTest extends TestCase
{
    public function testGetTypeReturnsEose(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame(RelayMessageType::Eose, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = new EoseMessage($subId);

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame(['EOSE', 'sub-1'], $message->toArray());
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $this->assertSame('["EOSE","sub-1"]', $message->toJson());
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $message = EoseMessage::tryFromArray(['EOSE', 'sub-1']) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(RelayMessageType::Eose, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(EoseMessage::tryFromArray(['EOSE']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(EoseMessage::tryFromArray(['CLOSED', 'sub-1']));
    }

    public function testTryFromJsonReadsTheSubscriptionIdBesideAHintArray(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s",["finish"]]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('s', (string) $message->getSubscriptionId());
    }

    public function testTryFromJsonReadsTheHintArray(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s",["auth","finish"]]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(['auth', 'finish'], $message->getHints());
    }

    public function testTryFromJsonKeepsAHintNip67DoesNotDefine(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s",["someday"]]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(['someday'], $message->getHints());
    }

    public function testAnEoseWithoutAHintArrayHasNoHints(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s"]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame([], $message->getHints());
    }

    #[DataProvider('thirdElementsThatAreNoHintArray')]
    public function testAThirdElementThatIsNoArrayOfStringsIsIgnored(string $json): void
    {
        $message = EoseMessage::tryFromJson($json) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame([], $message->getHints());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thirdElementsThatAreNoHintArray(): iterable
    {
        yield 'a string' => ['["EOSE","s","auth"]'];
        yield 'an array holding a number' => ['["EOSE","s",["auth",1]]'];
        yield 'an object' => ['["EOSE","s",{"0":"auth"}]'];
    }

    public function testToArrayWritesTheHintArray(): void
    {
        $message = new EoseMessage(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), ['auth']);

        $this->assertSame(['EOSE', 'sub-1', ['auth']], $message->toArray());
    }

    public function testHintingWritesTheHintArray(): void
    {
        $message = EoseMessage::hinting(SubscriptionId::tryFromString('s') ?? throw new RuntimeException('Expected a valid subscription ID'), EoseHint::Auth);

        $this->assertSame('["EOSE","s",["auth"]]', $message->toJson());
    }

    public function testHintingWritesEveryHintInTheOrderGiven(): void
    {
        $message = EoseMessage::hinting(SubscriptionId::tryFromString('s') ?? throw new RuntimeException('Expected a valid subscription ID'), EoseHint::Finish, EoseHint::Auth);

        $this->assertSame(['finish', 'auth'], $message->getHints());
    }

    public function testHasHintReadsAHintTheRelayWrote(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s",["finish","auth"]]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertTrue($message->hasHint(EoseHint::Auth));
    }

    public function testHasHintIsFalseForAHintTheMessageDoesNotCarry(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s",["finish"]]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertFalse($message->hasHint(EoseHint::Auth));
    }

    public function testAnEoseWithoutAHintArrayHasNoAuthHint(): void
    {
        $message = EoseMessage::tryFromJson('["EOSE","s"]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertFalse($message->hasHint(EoseHint::Auth));
    }

    public function testAHintArrayRoundTrips(): void
    {
        $this->assertSame('["EOSE","s",["auth","someday"]]', EoseMessage::tryFromJson('["EOSE","s",["auth","someday"]]')?->toJson());
    }

    public function testAnEmptyHintArrayIsWrittenBackWithoutOne(): void
    {
        $this->assertSame('["EOSE","s"]', EoseMessage::tryFromJson('["EOSE","s",[]]')?->toJson());
    }

    public function testTryFromArrayReturnsNullWithoutASubscriptionId(): void
    {
        $this->assertNull(EoseMessage::tryFromArray(['EOSE']));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new EoseMessage(SubscriptionId::tryFromString('my-subscription') ?? throw new RuntimeException('Expected a valid subscription ID'));

        $restored = EoseMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
    }

    public function testTryFromJsonRefusesAnObjectKeyedLikeTheMessage(): void
    {
        $this->assertNull(EoseMessage::tryFromJson('{"0":"EOSE","1":"s"}'));
    }
}
