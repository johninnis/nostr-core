<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\Nip19Codec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip19CodecTest extends TestCase
{
    private const PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const EVENT_ID_HEX = '6c4b0b8e1f9c7e9a5d2f1a0b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d';
    private const ADDRESSABLE_KIND = 30023;
    private const IDENTIFIER = 'my-article';

    private Nip19Codec $codec;

    protected function setUp(): void
    {
        $this->codec = new Nip19Codec();
    }

    public function testDecodeNpubReturnsPubkeyEntity(): void
    {
        $pubkey = $this->pubkey();

        $entity = $this->codec->decodeComplexEntity($pubkey->toBech32());

        $this->assertNotNull($entity);
        $this->assertSame(Nip19EntityType::Pubkey, $entity->getType());
        $this->assertNotNull($entity->getPublicKey());
        $this->assertTrue($entity->getPublicKey()->equals($pubkey));
    }

    public function testDecodeNoteReturnsEventEntity(): void
    {
        $eventId = $this->eventId();

        $entity = $this->codec->decodeComplexEntity($eventId->toBech32());

        $this->assertNotNull($entity);
        $this->assertSame(Nip19EntityType::Event, $entity->getType());
        $this->assertNotNull($entity->getEventId());
        $this->assertTrue($entity->getEventId()->equals($eventId));
    }

    public function testDecodeNpubWithWrongLengthPayloadReturnsNull(): void
    {
        $malformed = Bech32Codec::encode('npub', random_bytes(16));

        $this->assertNull($this->codec->decodeComplexEntity($malformed));
    }

    public function testDecodeNoteWithWrongLengthPayloadReturnsNull(): void
    {
        $malformed = Bech32Codec::encode('note', random_bytes(16));

        $this->assertNull($this->codec->decodeComplexEntity($malformed));
    }

    public function testDecodeNprofileReturnsProfileEntity(): void
    {
        $pubkey = $this->pubkey();
        $nprofile = Bech32Codec::encode('nprofile', $this->tlvEntry(0, $pubkey->toBytes()));

        $entity = $this->codec->decodeComplexEntity($nprofile);

        $this->assertNotNull($entity);
        $this->assertSame(Nip19EntityType::Profile, $entity->getType());
        $this->assertNotNull($entity->getPublicKey());
        $this->assertTrue($entity->getPublicKey()->equals($pubkey));
    }

    public function testDecodeNeventReturnsEventEntityWithAuthorAndKind(): void
    {
        $eventId = $this->eventId();
        $pubkey = $this->pubkey();
        $bytes = $this->tlvEntry(0, $eventId->toBytes())
            .$this->tlvEntry(2, $pubkey->toBytes())
            .$this->tlvEntry(3, pack('N', EventKind::TEXT_NOTE));
        $nevent = Bech32Codec::encode('nevent', $bytes);

        $entity = $this->codec->decodeComplexEntity($nevent);

        $this->assertNotNull($entity);
        $this->assertSame(Nip19EntityType::Event, $entity->getType());
        $this->assertNotNull($entity->getEventId());
        $this->assertTrue($entity->getEventId()->equals($eventId));
        $this->assertNotNull($entity->getPublicKey());
        $this->assertTrue($entity->getPublicKey()->equals($pubkey));
        $this->assertNotNull($entity->getKind());
        $this->assertSame(EventKind::TEXT_NOTE, $entity->getKind()->toInt());
    }

    public function testDecodeNaddrReturnsAddressEntity(): void
    {
        $pubkey = $this->pubkey();
        $naddr = $this->codec->encodeAddressableEvent(self::IDENTIFIER, $pubkey, self::ADDRESSABLE_KIND);

        $entity = $this->codec->decodeComplexEntity($naddr);

        $this->assertNotNull($entity);
        $this->assertSame(Nip19EntityType::Address, $entity->getType());
        $this->assertSame(self::IDENTIFIER, $entity->getIdentifier());
        $this->assertNotNull($entity->getPublicKey());
        $this->assertTrue($entity->getPublicKey()->equals($pubkey));
        $this->assertNotNull($entity->getKind());
        $this->assertSame(self::ADDRESSABLE_KIND, $entity->getKind()->toInt());
    }

    public function testDecodeInvalidBech32ReturnsNull(): void
    {
        $this->assertNull($this->codec->decodeComplexEntity('not-a-bech32-string'));
    }

    public function testDecodeUnknownPrefixReturnsNull(): void
    {
        $unknown = Bech32Codec::encode('nsec', $this->pubkey()->toBytes());

        $this->assertNull($this->codec->decodeComplexEntity($unknown));
    }

    public function testParseEventReferenceAcceptsHexEventId(): void
    {
        $reference = $this->codec->parseEventReference(self::EVENT_ID_HEX);

        $this->assertInstanceOf(EventId::class, $reference);
        $this->assertSame(self::EVENT_ID_HEX, $reference->toHex());
    }

    public function testParseEventReferenceAcceptsNote(): void
    {
        $eventId = $this->eventId();

        $reference = $this->codec->parseEventReference($eventId->toBech32());

        $this->assertInstanceOf(EventId::class, $reference);
        $this->assertTrue($reference->equals($eventId));
    }

    public function testParseEventReferenceAcceptsNaddrAsCoordinate(): void
    {
        $pubkey = $this->pubkey();
        $naddr = $this->codec->encodeAddressableEvent(self::IDENTIFIER, $pubkey, self::ADDRESSABLE_KIND);

        $reference = $this->codec->parseEventReference($naddr);

        $this->assertInstanceOf(EventCoordinate::class, $reference);
        $this->assertSame(self::ADDRESSABLE_KIND, $reference->getKind()->toInt());
        $this->assertSame(self::IDENTIFIER, $reference->getIdentifier());
        $this->assertTrue($reference->getPubkey()->equals($pubkey));
    }

    public function testParseEventReferenceReturnsNullForGarbage(): void
    {
        $this->assertNull($this->codec->parseEventReference('not-a-reference'));
    }

    public function testEncodeAddressableEventCarriesRelayHint(): void
    {
        $pubkey = $this->pubkey();
        $naddr = $this->codec->encodeAddressableEvent(
            self::IDENTIFIER,
            $pubkey,
            self::ADDRESSABLE_KIND,
            ['wss://relay.example.com'],
        );

        $entity = $this->codec->decodeComplexEntity($naddr);

        $this->assertNotNull($entity);
        $this->assertFalse($entity->getRelays()->isEmpty());
    }

    private function tlvEntry(int $type, string $value): string
    {
        return pack('CC', $type, strlen($value)).$value;
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::fromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');
    }

    private function eventId(): EventId
    {
        return EventId::fromHex(self::EVENT_ID_HEX) ?? throw new RuntimeException('Invalid test event id');
    }
}
