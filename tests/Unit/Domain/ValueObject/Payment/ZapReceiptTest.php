<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ZapReceiptTest extends TestCase
{
    private const SENDER_PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const RECIPIENT_PUBKEY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const RECEIPT_PUBKEY = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public function testValidReceiptWithAllFields(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => 'Great post!',
            'tags' => [['amount', '21000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['P', self::SENDER_PUBKEY],
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc210n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $senderPubkey = $receipt->getSenderPubkey();
        $this->assertNotNull($senderPubkey);
        $this->assertSame(self::SENDER_PUBKEY, $senderPubkey->toHex());
        $recipientPubkey = $receipt->getRecipientPubkey();
        $this->assertNotNull($recipientPubkey);
        $this->assertSame(self::RECIPIENT_PUBKEY, $recipientPubkey->toHex());
        $this->assertSame(21000, $receipt->getAmount()->toMillisats());
        $this->assertSame('Great post!', $receipt->getMessage());
    }

    public function testSenderFallsBackToZapRequestPubkey(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '5000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc50n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $senderPubkey = $receipt->getSenderPubkey();
        $this->assertNotNull($senderPubkey);
        $this->assertSame(self::SENDER_PUBKEY, $senderPubkey->toHex());
    }

    public function testMissingRecipientReturnsNullRecipientPubkey(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '1000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['P', self::SENDER_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc10n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getRecipientPubkey());
    }

    public function testAmountDerivesFromBolt11(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc100n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertSame(10000, $receipt->getAmount()->toMillisats());
        $this->assertSame(10, $receipt->getAmount()->toSats());
    }

    public function testZapRequestAmountTagMatchingBolt11Parses(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '10000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc100n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertSame(10000, $receipt->getAmount()->toMillisats());
    }

    public function testZapRequestAmountTagDisagreeingWithBolt11ReturnsNull(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '50000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc100n1p...'],
        ]);

        $this->assertNull(ZapReceipt::fromEvent($event));
    }

    public function testForgedZapRequestAmountWithoutBolt11ReturnsNull(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '9223372036854775807']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
        ]);

        $this->assertNull(ZapReceipt::fromEvent($event));
    }

    public function testReceiptLevelAmountTagIsIgnored(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['amount', '2100000000000000000'],
            ['bolt11', 'lnbc100n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertSame(10000, $receipt->getAmount()->toMillisats());
    }

    public function testBolt11AboveOneBtcReturnsNull(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc21m1p...'],
        ]);

        $this->assertNotNull(ZapReceipt::fromEvent($event));

        $oversized = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc2100m1p...'],
        ]);

        $this->assertNull(ZapReceipt::fromEvent($oversized));
    }

    public function testNoBolt11ReturnsNull(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
        ]);

        $this->assertNull(ZapReceipt::fromEvent($event));
    }

    public function testMessageExtraction(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => 'Keep up the good work!',
            'tags' => [['amount', '1000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc10n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertSame('Keep up the good work!', $receipt->getMessage());
    }

    public function testEmptyMessageReturnsNull(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '1000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc10n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getMessage());
    }

    public function testNonZapReceiptReturnsNull(): void
    {
        $event = new Event(
            PublicKey::fromHex(self::RECEIPT_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('hello'),
        );

        $this->assertNull(ZapReceipt::fromEvent($event));
    }

    public function testMalformedDescriptionJsonReturnsGracefulDefaults(): void
    {
        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', 'not valid json'],
            ['bolt11', 'lnbc100n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getSenderPubkey());
        $recipientPubkey = $receipt->getRecipientPubkey();
        $this->assertNotNull($recipientPubkey);
        $this->assertSame(self::RECIPIENT_PUBKEY, $recipientPubkey->toHex());
        $this->assertSame(10000, $receipt->getAmount()->toMillisats());
        $this->assertNull($receipt->getMessage());
    }

    public function testNoDescriptionTagReturnsGracefulDefaults(): void
    {
        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['bolt11', 'lnbc100n1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getSenderPubkey());
        $this->assertSame(10000, $receipt->getAmount()->toMillisats());
        $this->assertNull($receipt->getMessage());
    }

    private function buildReceiptEvent(array $rawTags): Event
    {
        return new Event(
            PublicKey::fromHex(self::RECEIPT_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::ZAP_RECEIPT),
            TagCollectionMother::fromRaw($rawTags),
            EventContent::fromString(''),
        );
    }
}
