<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NutzapTest extends TestCase
{
    private const SENDER_PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const RECIPIENT_PUBKEY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testValidNutzapWithAllFields(): void
    {
        $event = $this->buildNutzapEvent(
            [
                ['p', self::RECIPIENT_PUBKEY],
                ['proof', json_encode(['amount' => 21, 'id' => 'abc', 'secret' => 'xyz', 'C' => '02...'])],
            ],
            'Great post!',
        );

        $nutzap = Nutzap::fromEvent($event);

        $this->assertNotNull($nutzap);
        $senderPubkey = $nutzap->getSenderPubkey();
        $this->assertNotNull($senderPubkey);
        $this->assertSame(self::SENDER_PUBKEY, $senderPubkey->toHex());
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

        $nutzap = Nutzap::fromEvent($event);

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

        $nutzap = Nutzap::fromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertNull($nutzap->getAmount());
    }

    public function testEmptyContentReturnsNullMessage(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 1, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $nutzap = Nutzap::fromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertNull($nutzap->getMessage());
    }

    public function testWrongKindReturnsNull(): void
    {
        $event = new Event(
            PublicKey::fromHex(self::SENDER_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('hello'),
        );

        $this->assertNull(Nutzap::fromEvent($event));
    }

    public function testMsatUnit(): void
    {
        $event = $this->buildNutzapEvent([
            ['p', self::RECIPIENT_PUBKEY],
            ['proof', json_encode(['amount' => 21000, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
            ['unit', 'msat'],
        ]);

        $nutzap = Nutzap::fromEvent($event);

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

        $nutzap = Nutzap::fromEvent($event);

        $this->assertNotNull($nutzap);
        $amount = $nutzap->getAmount();
        $this->assertNotNull($amount);
        $this->assertSame(5, $amount->toSats());
    }

    public function testMissingRecipientReturnsNullRecipientPubkey(): void
    {
        $event = $this->buildNutzapEvent([
            ['proof', json_encode(['amount' => 10, 'id' => 'a', 'secret' => 's', 'C' => '02a'])],
        ]);

        $nutzap = Nutzap::fromEvent($event);

        $this->assertNotNull($nutzap);
        $this->assertNull($nutzap->getRecipientPubkey());
    }

    private function buildNutzapEvent(array $rawTags, string $content = ''): Event
    {
        return new Event(
            PublicKey::fromHex(self::SENDER_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::nutzap(),
            TagCollection::fromArray($rawTags),
            EventContent::fromString($content),
        );
    }
}
