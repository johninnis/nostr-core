<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\PaymentReceiptParser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentReceiptParserTest extends TestCase
{
    private const SENDER_PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const RECIPIENT_PUBKEY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testParsesZapReceiptEvent(): void
    {
        $zapRequest = json_encode([
            'pubkey' => self::SENDER_PUBKEY,
            'content' => 'Great post!',
            'tags' => [['amount', '21000']],
        ], JSON_THROW_ON_ERROR);

        $event = $this->buildEvent(EventKind::ZAP_RECEIPT, [
            ['p', self::RECIPIENT_PUBKEY],
            ['description', $zapRequest],
            ['bolt11', 'lnbc210n1p...'],
        ]);

        $this->assertInstanceOf(ZapReceipt::class, PaymentReceiptParser::fromEvent($event));
    }

    public function testParsesNutzapEvent(): void
    {
        $event = $this->buildEvent(EventKind::NUTZAP, [
            ['p', self::RECIPIENT_PUBKEY],
        ]);

        $this->assertInstanceOf(Nutzap::class, PaymentReceiptParser::fromEvent($event));
    }

    public function testReturnsNullForNonPaymentEvent(): void
    {
        $event = $this->buildEvent(EventKind::TEXT_NOTE, []);

        $this->assertNull(PaymentReceiptParser::fromEvent($event));
    }

    /**
     * @param list<list<string>> $rawTags
     */
    private function buildEvent(int $kind, array $rawTags): Event
    {
        return new Event(
            PublicKey::fromHex(self::SENDER_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt($kind),
            [] === $rawTags ? new TagCollection() : TagCollectionMother::fromRaw($rawTags),
            EventContent::fromString(''),
        );
    }
}
