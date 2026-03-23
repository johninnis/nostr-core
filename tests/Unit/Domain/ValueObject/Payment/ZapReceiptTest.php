<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

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
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $senderPubkey = $receipt->getSenderPubkey();
        $this->assertNotNull($senderPubkey);
        $this->assertSame(self::SENDER_PUBKEY, $senderPubkey->toHex());
        $recipientPubkey = $receipt->getRecipientPubkey();
        $this->assertNotNull($recipientPubkey);
        $this->assertSame(self::RECIPIENT_PUBKEY, $recipientPubkey->toHex());
        $amount = $receipt->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(21000, $amount->toMillisats());
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
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getRecipientPubkey());
    }

    public function testAmountFromZapRequestTags(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [['amount', '50000']],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['amount', '99999'],
            ['bolt11', 'lnbc100u1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $amount = $receipt->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(50000, $amount->toMillisats());
    }

    public function testAmountFallsBackToReceiptAmountTag(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => '',
            'tags' => [],
        ]);

        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['amount', '75000'],
            ['bolt11', 'lnbc100u1p...'],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $amount = $receipt->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(75000, $amount->toMillisats());
    }

    public function testAmountFallsBackToBolt11(): void
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
        $amount = $receipt->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(10000, $amount->toMillisats());
        $this->assertSame(10, $amount->toSats());
    }

    public function testNoAmountReturnsNull(): void
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

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getAmount());
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
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getMessage());
    }

    public function testNonZapReceiptReturnsNull(): void
    {
        $event = new Event(
            PublicKey::fromHex(self::RECEIPT_PUBKEY) ?? throw new \RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::textNote(),
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
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getSenderPubkey());
        $recipientPubkey = $receipt->getRecipientPubkey();
        $this->assertNotNull($recipientPubkey);
        $this->assertSame(self::RECIPIENT_PUBKEY, $recipientPubkey->toHex());
        $this->assertNull($receipt->getAmount());
        $this->assertNull($receipt->getMessage());
    }

    public function testNoDescriptionTagReturnsGracefulDefaults(): void
    {
        $event = $this->buildReceiptEvent([
            ['p', self::RECIPIENT_PUBKEY],
        ]);

        $receipt = ZapReceipt::fromEvent($event);

        $this->assertNotNull($receipt);
        $this->assertNull($receipt->getSenderPubkey());
        $this->assertNull($receipt->getAmount());
        $this->assertNull($receipt->getMessage());
    }

    private function buildReceiptEvent(array $rawTags): Event
    {
        return new Event(
            PublicKey::fromHex(self::RECEIPT_PUBKEY) ?? throw new \RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::zapReceipt(),
            TagCollection::fromArray($rawTags),
            EventContent::fromString(''),
        );
    }
}
