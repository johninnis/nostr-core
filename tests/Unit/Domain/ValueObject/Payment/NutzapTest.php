<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NutzapTest extends TestCase
{
    private const string SENDER_PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string RECIPIENT_PUBKEY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testValidNutzapWithAllFields(): void
    {
        $event = $this->buildNutzapEvent(
            [
                ['p', self::RECIPIENT_PUBKEY],
                ['proof', json_encode(['amount' => 21, 'id' => 'abc', 'secret' => 'xyz', 'C' => '02...'])],
            ],
            'Great post!',
        );

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertSame(self::SENDER_PUBKEY, $nutzap->getSenderPubkey()->toHex());
        $recipientPubkey = $nutzap->getRecipientPubkey();
        $this->assertNotNull($recipientPubkey);
        $this->assertSame(self::RECIPIENT_PUBKEY, $recipientPubkey->toHex());
        $amount = $nutzap->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(21, $amount->toSats());
        $this->assertSame('Great post!', $nutzap->getMessage());
    }

    public function testMultipleProofsSum(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 10, 'id' => 'a', 'secret' => 's1', 'C' => '02a'])],
            ['proof', json_encode(['amount' => 5, 'id' => 'b', 'secret' => 's2', 'C' => '02b'])],
            ['proof', json_encode(['amount' => 7, 'id' => 'c', 'secret' => 's3', 'C' => '02c'])],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $amount = $nutzap->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(22, $amount->toSats());
    }

    public function testNoProofsReturnsNullAmount(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertNull($nutzap->getAmount());
    }

    public function testEmptyContentReturnsNullMessage(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 1, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertNull($nutzap->getMessage());
    }

    public function testWrongKindReturnsNull(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex(self::SENDER_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('hello'),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        ));

        $this->assertNull(Nutzap::tryFromEvent($event));
    }

    public function testMsatUnit(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 21000, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
            ['unit', 'msat'],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $amount = $nutzap->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(21000, $amount->toMillisats());
        $this->assertSame(21, $amount->toSats());
    }

    public function testMalformedProofJsonSkipped(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', 'not valid json'],
            ['proof', json_encode(['amount' => 5, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $amount = $nutzap->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(5, $amount->toSats());
    }

    public function testAProofWrittenAsAJsonArrayStatesNoAmount(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode([['amount' => 21, 'id' => 'a', 'secret' => 's1', 'C' => '02a']])],
            ['proof', json_encode(['amount' => 5, 'id' => 'b', 'secret' => 's2', 'C' => '02b'])],
        ]);

        $this->assertSame(5, Nutzap::tryFromEvent($event)?->getAmount()?->toSats());
    }

    public function testTotalAboveCapReturnsNull(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 100_000_000, 'id' => 'a', 'secret' => 's1', 'C' => '02a'])],
            ['proof', json_encode(['amount' => 1, 'id' => 'b', 'secret' => 's2', 'C' => '02b'])],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event));
    }

    public function testForgedHugeProofAmountReturnsNull(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => PHP_INT_MAX, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function proofAmountsThatAreNotIntegers(): iterable
    {
        yield 'fraction' => [1.5];
        yield 'decimal string' => ['5'];
        yield 'exponent string' => ['1e3'];
        yield 'huge decimal string' => ['9223372036854775807'];
    }

    #[DataProvider('proofAmountsThatAreNotIntegers')]
    public function testAProofWhoseAmountIsNotAJsonIntegerStatesNoAmount(mixed $amount): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => $amount, 'id' => 'a', 'secret' => 's1', 'C' => '02a'])],
            ['proof', json_encode(['amount' => 10, 'id' => 'b', 'secret' => 's2', 'C' => '02b'])],
        ]);

        $this->assertSame(10, Nutzap::tryFromEvent($event)?->getAmount()?->toSats());
    }

    public function testNegativeProofAmountReturnsNull(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => -5, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event));
    }

    public function testTotalAtCapParses(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 100_000_000, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $amount = $nutzap->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(100_000_000, $amount->toSats());
    }

    public function testMissingRecipientReturnsNullRecipientPubkey(): void
    {
        $event = $this->buildNutzapEvent([
            ['proof', json_encode(['amount' => 10, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $nutzap = Nutzap::tryFromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertNull($nutzap->getRecipientPubkey());
    }

    public function testUnitTagsThatDisagreeReturnNull(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 21, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
            ['unit', 'sat'],
            ['unit', 'msat'],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event));
    }

    public function testUnitTagsThatDisagreeRefuseANutzapWithoutProofs(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['unit', 'sat'],
            ['unit', 'usd'],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event));
    }

    public function testANonBitcoinUnitKeepsTheNutzap(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 21, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
            ['unit', 'usd'],
        ]);

        $this->assertNotNull(Nutzap::tryFromEvent($event));
    }

    public function testANonBitcoinUnitStatesNoAmount(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 21, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
            ['unit', 'eur'],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event)?->getAmount());
    }

    public function testARepeatedIdenticalProofIsOneProof(): void
    {
        $proof = json_encode(['amount' => 21, 'id' => 'a', 'secret' => 's', 'C' => '02a']);
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', $proof],
            ['proof', $proof],
        ]);

        $this->assertSame(21, Nutzap::tryFromEvent($event)?->getAmount()?->toSats());
    }

    public function testRepeatedUnitTagIsOneClaim(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 21000, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
            ['unit', 'msat'],
            ['unit', 'msat'],
        ]);

        $this->assertSame(21000, Nutzap::tryFromEvent($event)?->getAmount()?->toMillisats());
    }

    public function testRecipientTagsThatDisagreeNameNoRecipient(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['p', str_repeat('ef', 32)],
            ['proof', json_encode(['amount' => 10, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $this->assertNull(Nutzap::tryFromEvent($event)?->getRecipientPubkey());
    }

    /**
     * @param list<list<string|false>> $rawTags
     */
    private function buildNutzapEvent(array $rawTags, string $content = ''): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex(self::SENDER_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::NUTZAP),
            EventContent::fromString($content),
            TagCollectionMother::fromRaw($rawTags),
            Timestamp::fromInt(1700000000),
        ));
    }
}
