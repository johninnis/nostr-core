<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\Nip19Codec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Note;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Npub;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip19CodecTest extends TestCase
{
    private const string PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const string EVENT_ID_HEX = '6c4b0b8e1f9c7e9a5d2f1a0b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d';
    private const int ADDRESSABLE_KIND = 30023;
    private const string IDENTIFIER = 'my-article';

    private Nip19Codec $codec;

    protected function setUp(): void
    {
        $this->codec = new Nip19Codec();
    }

    public function testDecodeNpubReturnsNpubLeaf(): void
    {
        $entity = $this->codec->decodeComplexEntity($this->pubkey()->toBech32());

        $this->assertInstanceOf(Npub::class, $entity);
        $this->assertSame(Nip19EntityType::Pubkey, $entity->type());
        $this->assertTrue($entity->getPublicKey()->equals($this->pubkey()));
    }

    // Deliberate: note and nevent were both reported as Event before the leaves were split; this pins them apart
    public function testDecodeNoteReturnsNoteLeafDistinctFromNevent(): void
    {
        $note = $this->codec->decodeComplexEntity($this->eventId()->toBech32());
        $nevent = $this->codec->decodeComplexEntity($this->nevent()->toBech32());

        $this->assertInstanceOf(Note::class, $note);
        $this->assertInstanceOf(Nevent::class, $nevent);
        $this->assertSame(Nip19EntityType::Note, $note->type());
        $this->assertSame(Nip19EntityType::Event, $nevent->type());
    }

    public function testDecodeNprofileReturnsNprofileLeafWithRelays(): void
    {
        $entity = $this->codec->decodeComplexEntity($this->nprofile()->toBech32());

        $this->assertInstanceOf(Nprofile::class, $entity);
        $this->assertTrue($entity->getPublicKey()->equals($this->pubkey()));
        $this->assertFalse($entity->getRelays()->isEmpty());
    }

    public function testDecodeNeventCarriesAuthorAndKind(): void
    {
        $nevent = Nevent::tryFromEventId($this->eventId(), new RelayUrlCollection(), $this->pubkey(), EventKind::fromInt(EventKind::TEXT_NOTE));
        $this->assertNotNull($nevent);

        $entity = $this->codec->decodeComplexEntity($nevent->toBech32());

        $this->assertInstanceOf(Nevent::class, $entity);
        $this->assertNotNull($entity->getAuthor());
        $this->assertTrue($entity->getAuthor()->equals($this->pubkey()));
        $this->assertSame(EventKind::TEXT_NOTE, $entity->getKind()?->toInt());
    }

    public function testDecodeNaddrReturnsNaddrLeafCarryingItsCoordinate(): void
    {
        $entity = $this->codec->decodeComplexEntity($this->naddr()->toBech32());

        $this->assertInstanceOf(Naddr::class, $entity);
        $this->assertSame(Nip19EntityType::Address, $entity->type());
        $this->assertSame(self::IDENTIFIER, $entity->getCoordinate()->getIdentifier());
        $this->assertSame(self::ADDRESSABLE_KIND, $entity->getCoordinate()->getKind()->toInt());
        $this->assertTrue($entity->getCoordinate()->getPubkey()->equals($this->pubkey()));
    }

    // Deliberate: NIP-19 requires author and kind on an naddr, so a payload carrying only an identifier has no coordinate — see ADR-0060
    public function testDecodeNaddrReturnsNullWhenAuthorAndKindAreAbsent(): void
    {
        $identifierOnly = chr(0).chr(3).'abc';

        $this->assertNull($this->codec->decodeComplexEntity(Bech32Codec::encode('naddr', $identifierOnly)));
        $this->assertNull($this->codec->parseEventReference(Bech32Codec::encode('naddr', $identifierOnly)));
    }

    public function testDecodeReturnsNullForTruncatedTlv(): void
    {
        foreach (['nprofile', 'nevent', 'naddr'] as $hrp) {
            $this->assertNull($this->codec->decodeComplexEntity(Bech32Codec::encode($hrp, self::truncatedTlv())));
        }
    }

    public function testDecodeReturnsNullForTrailingTypeByteWithoutLength(): void
    {
        $danglingType = chr(0).chr(1).'x'.chr(1);

        $this->assertNull($this->codec->decodeComplexEntity(Bech32Codec::encode('nprofile', $danglingType)));
    }

    public function testDecodeReturnsNullForInvalidBech32(): void
    {
        $this->assertNull($this->codec->decodeComplexEntity('not-a-bech32-string'));
    }

    public function testDecodeReturnsNullForUnknownPrefix(): void
    {
        $this->assertNull($this->codec->decodeComplexEntity(Bech32Codec::encode('nsec', $this->pubkey()->toBytes())));
    }

    public function testParseEventReferenceAcceptsHexEventId(): void
    {
        $reference = $this->codec->parseEventReference(self::EVENT_ID_HEX);

        $this->assertInstanceOf(EventId::class, $reference);
        $this->assertSame(self::EVENT_ID_HEX, $reference->toHex());
    }

    public function testParseEventReferenceAcceptsNote(): void
    {
        $reference = $this->codec->parseEventReference($this->eventId()->toBech32());

        $this->assertInstanceOf(EventId::class, $reference);
        $this->assertTrue($reference->equals($this->eventId()));
    }

    public function testParseEventReferenceAcceptsNaddrAsCoordinate(): void
    {
        $reference = $this->codec->parseEventReference($this->naddr()->toBech32());

        $this->assertInstanceOf(EventCoordinate::class, $reference);
        $this->assertSame(self::ADDRESSABLE_KIND, $reference->getKind()->toInt());
        $this->assertSame(self::IDENTIFIER, $reference->getIdentifier());
        $this->assertTrue($reference->getPubkey()->equals($this->pubkey()));
    }

    public function testParseEventReferenceReturnsNullForGarbage(): void
    {
        $this->assertNull($this->codec->parseEventReference('not-a-reference'));
    }

    private static function truncatedTlv(): string
    {
        return chr(0).chr(50).'abc';
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');
    }

    private function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID_HEX) ?? throw new RuntimeException('Invalid test event id');
    }

    private function nevent(): Nevent
    {
        return Nevent::tryFromEventId($this->eventId()) ?? throw new RuntimeException('Invalid test nevent');
    }

    private function nprofile(): Nprofile
    {
        $relay = RelayUrl::tryFromString('wss://relay.example.com') ?? throw new RuntimeException('Invalid test relay');

        return Nprofile::tryFromPublicKey($this->pubkey(), new RelayUrlCollection([$relay]))
            ?? throw new RuntimeException('Invalid test nprofile');
    }

    private function naddr(): Naddr
    {
        $coordinate = EventCoordinate::tryFromParts(self::ADDRESSABLE_KIND, self::PUBKEY_HEX, self::IDENTIFIER)
            ?? throw new RuntimeException('Invalid test coordinate');

        return Naddr::tryFromCoordinate($coordinate) ?? throw new RuntimeException('Invalid test naddr');
    }
}
