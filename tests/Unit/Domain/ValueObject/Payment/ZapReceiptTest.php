<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ZapReceiptTest extends TestCase
{
    private const string SENDER_PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string RECIPIENT_PUBKEY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string RECEIPT_PUBKEY = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
    private const string SOMEONE_ELSE_PUBKEY = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

    public function testAReceiptReadsItsFieldsFromTheReceiptAndTheZapRequest(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $this->zapRequest('Great post!', [['amount', '21000']])],
            ['bolt11', 'lnbc210n1p...'],
        ]));

        $this->assertNotNull($receipt);
        $this->assertSame(self::SENDER_PUBKEY, $receipt->getSenderPubkey()->toHex());
        $this->assertSame(self::RECIPIENT_PUBKEY, $receipt->getRecipientPubkey()?->toHex());
        $this->assertSame(21000, $receipt->getAmount()->toMillisats());
        $this->assertSame('Great post!', $receipt->getMessage());
    }

    public function testTheSenderIsTheZapRequestAuthorEvenWhenTheReceiptNamesSomeoneElse(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['P', self::SOMEONE_ELSE_PUBKEY],
            ['description', $this->zapRequest()],
            ['bolt11', 'lnbc10n1p...'],
        ]));

        $this->assertSame(self::SENDER_PUBKEY, $receipt?->getSenderPubkey()->toHex());
    }

    public function testTheReceiptAndTheZapRequestAreBothHeld(): void
    {
        $event = $this->receipt([['description', $this->zapRequest()], ['bolt11', 'lnbc10n1p...']]);

        $receipt = ZapReceipt::tryFromEvent($event);

        $this->assertSame($event, $receipt?->getReceipt());
        $this->assertTrue($receipt->getZapRequest()->getKind()->is(EventKind::ZAP_REQUEST));
    }

    public function testMissingRecipientReturnsNullRecipientPubkey(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest()],
            ['bolt11', 'lnbc10n1p...'],
        ]));

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getRecipientPubkey());
    }

    public function testRecipientTagsThatDisagreeNameNoRecipient(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest()],
            ['bolt11', 'lnbc10n1p...'],
            ['p', str_repeat('ab', 32)],
            ['p', str_repeat('cd', 32)],
        ]));

        $this->assertNull($receipt?->getRecipientPubkey());
    }

    public function testAmountDerivesFromBolt11(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [])],
            ['bolt11', 'lnbc100n1p...'],
        ]));

        $this->assertSame(10000, $receipt?->getAmount()->toMillisats());
    }

    public function testZapRequestAmountTagDisagreeingWithBolt11ReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [['amount', '50000']])],
            ['bolt11', 'lnbc100n1p...'],
        ])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsThatAreNotDecimalIntegers(): iterable
    {
        yield 'exponent' => ['1e4'];
        yield 'fraction' => ['10000.0'];
        yield 'sign' => ['+10000'];
        yield 'leading whitespace' => [' 10000'];
        yield 'hexadecimal' => ['0x2710'];
    }

    #[DataProvider('amountsThatAreNotDecimalIntegers')]
    public function testZapRequestAmountThatIsNotADecimalIntegerReturnsNull(string $amount): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [['amount', $amount]])],
            ['bolt11', 'lnbc100n1p...'],
        ])));
    }

    public function testReceiptLevelAmountTagIsIgnored(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [])],
            ['amount', '2100000000000000000'],
            ['bolt11', 'lnbc100n1p...'],
        ]));

        $this->assertSame(10000, $receipt?->getAmount()->toMillisats());
    }

    public function testBolt11AboveOneBtcReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [])],
            ['bolt11', 'lnbc2100m1p...'],
        ])));
    }

    public function testNoBolt11ReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [['amount', '9223372036854775807']])],
        ])));
    }

    public function testTwoDisagreeingBolt11TagsReturnNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('', [])],
            ['bolt11', 'lnbc100n1p...'],
            ['bolt11', 'lnbc210n1p...'],
        ])));
    }

    public function testAnEmptyMessageReturnsNull(): void
    {
        $receipt = ZapReceipt::tryFromEvent($this->receipt([
            ['description', $this->zapRequest('')],
            ['bolt11', 'lnbc10n1p...'],
        ]));

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getMessage());
    }

    public function testANonZapReceiptKindReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->event(self::RECEIPT_PUBKEY, EventKind::TEXT_NOTE, new TagCollection(), 'hello')));
    }

    public function testAReceiptWithNoDescriptionReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([['bolt11', 'lnbc100n1p...']])));
    }

    public function testADescriptionThatIsNotASignedEventReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', '{"pubkey":"'.self::SENDER_PUBKEY.'","content":"","tags":[]}'],
            ['bolt11', 'lnbc100n1p...'],
        ])));
    }

    public function testADescriptionThatIsNotAZapRequestReturnsNull(): void
    {
        $note = $this->event(self::SENDER_PUBKEY, EventKind::TEXT_NOTE, new TagCollection(), '')->toJson();

        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([['description', $note], ['bolt11', 'lnbc10n1p...']])));
    }

    // Deliberate: a second description tag is ambiguous, not resolvable by position — pinning the rejection stops it being "fixed" back to picking the first — see ADR-0079
    public function testAReceiptCarryingTwoDescriptionTagsReturnsNull(): void
    {
        $this->assertNull(ZapReceipt::tryFromEvent($this->receipt([
            ['description', 'not valid json'],
            ['description', $this->zapRequest()],
            ['bolt11', 'lnbc10n1p...'],
        ])));
    }

    /**
     * @param list<list<string>> $tags
     */
    private function zapRequest(string $content = 'hi', array $tags = [['amount', '1000']]): string
    {
        return $this->event(self::SENDER_PUBKEY, EventKind::ZAP_REQUEST, TagCollectionMother::fromRaw($tags), $content)->toJson();
    }

    /**
     * @param list<list<string>> $tags
     */
    private function receipt(array $tags): Event
    {
        return $this->event(self::RECEIPT_PUBKEY, EventKind::ZAP_RECEIPT, TagCollectionMother::fromRaw($tags), '');
    }

    private function event(string $pubkey, int $kind, TagCollection $tags, string $content): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex($pubkey) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt($kind),
            EventContent::fromString($content),
            $tags,
            Timestamp::fromInt(1700000000),
        ));
    }
}
