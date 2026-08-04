<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nip19Tlv;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NeventTest extends TestCase
{
    private const string EVENT_ID_HEX = '6c4b0b8e1f9c7e9a5d2f1a0b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d';
    private const string PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    public function testRoundTripsEveryOptionalField(): void
    {
        $relay = RelayUrl::tryFromString('wss://relay.example.com') ?? throw new RuntimeException('Invalid test relay');

        $nevent = Nevent::tryFromEventId(
            $this->eventId(),
            new RelayUrlCollection([$relay]),
            $this->pubkey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
        );
        $this->assertNotNull($nevent);

        $decoded = Nevent::tryFromBech32($nevent->toBech32());

        $this->assertNotNull($decoded);
        $this->assertTrue($decoded->getEventId()->equals($this->eventId()));
        $this->assertTrue($decoded->getAuthor()?->equals($this->pubkey()) ?? false);
        $this->assertSame(EventKind::TEXT_NOTE, $decoded->getKind()?->toInt());
        $this->assertSame(['wss://relay.example.com'], $decoded->getRelays()->toStrings());
    }

    // Deliberate: author is optional, so its absence is legal — but a present, malformed author record is corruption and must not be reported as absence — see ADR-0060
    public function testRejectsAPresentButMalformedAuthorRecord(): void
    {
        $payload = $this->specialRecord().pack('CC', Nip19Tlv::TYPE_AUTHOR, 8).'SHORTKEY';

        $this->assertNull(Nevent::tryFromBech32(Bech32Codec::encode(Nevent::HRP, $payload)));
    }

    public function testRejectsAPresentButMalformedKindRecord(): void
    {
        $payload = $this->specialRecord().pack('CC', Nip19Tlv::TYPE_KIND, 2)."\x00\x01";

        $this->assertNull(Nevent::tryFromBech32(Bech32Codec::encode(Nevent::HRP, $payload)));
    }

    // Deliberate: relay hints are a best-effort list, so an unusable one drops individually rather than discarding the event id with it — see ADR-0060
    public function testDropsAnUnusableRelayHintButKeepsTheEvent(): void
    {
        $payload = $this->specialRecord().pack('CC', Nip19Tlv::TYPE_RELAY, 11).'not a url!!';

        $decoded = Nevent::tryFromBech32(Bech32Codec::encode(Nevent::HRP, $payload));

        $this->assertNotNull($decoded);
        $this->assertTrue($decoded->getEventId()->equals($this->eventId()));
        $this->assertSame([], $decoded->getRelays()->toStrings());
    }

    // Deliberate: toBech32 is canonical output, not the bytes decoded — record order is normalised and unrecognised types are dropped; see the round-trip consequence in ADR-0060
    public function testReEncodesCanonicallyAndDropsUnrecognisedRecords(): void
    {
        $foreign = $this->specialRecord()
            .pack('CC', Nip19Tlv::TYPE_AUTHOR, 32).$this->pubkey()->toBytes()
            .pack('CC', Nip19Tlv::TYPE_KIND, 4).pack('N', EventKind::TEXT_NOTE)
            .pack('CC', Nip19Tlv::TYPE_RELAY, 23).'wss://relay.example.com'
            .pack('CC', 7, 5).'xxxxx';

        $input = Bech32Codec::encode(Nevent::HRP, $foreign);
        $decoded = Nevent::tryFromBech32($input);

        $this->assertNotNull($decoded);
        $this->assertNotSame($input, $decoded->toBech32());

        $reparsed = Nip19Tlv::tryFromBytes(Bech32Codec::decodeWithHrp($decoded->toBech32(), Nevent::HRP) ?? '');
        $this->assertNotNull($reparsed);
        $this->assertSame([], $reparsed->all(7));
        $this->assertSame('wss://relay.example.com', $reparsed->first(Nip19Tlv::TYPE_RELAY));
    }

    private function specialRecord(): string
    {
        return pack('CC', Nip19Tlv::TYPE_SPECIAL, 32).$this->eventId()->toBytes();
    }

    private function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID_HEX) ?? throw new RuntimeException('Invalid test event id');
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');
    }
}
