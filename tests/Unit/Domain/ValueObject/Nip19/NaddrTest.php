<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Service\Nip19Codec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Tests\Support\Bech32Mother;
use Innis\Nostr\Core\Tests\Support\RelayUrlMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NaddrTest extends TestCase
{
    private const string PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const int ADDRESSABLE_KIND = 30023;

    public function testRoundTripsThroughBech32(): void
    {
        $naddr = $this->naddr('my-article');
        $this->assertNotNull($naddr);

        $decoded = Naddr::tryFromBech32($naddr->toBech32());

        $this->assertNotNull($decoded);
        $this->assertSame('my-article', $decoded->getCoordinate()->getIdentifier());
    }

    public function testAcceptsIdentifierAtTheTlvLengthLimit(): void
    {
        $identifier = str_repeat('d', 255);
        $naddr = $this->naddr($identifier);

        $this->assertNotNull($naddr);
        $this->assertSame($identifier, Naddr::tryFromBech32($naddr->toBech32())?->getCoordinate()->getIdentifier());
    }

    public function testRejectsIdentifierBeyondTheTlvLengthByte(): void
    {
        $this->assertNull($this->naddr(str_repeat('d', 256)));
    }

    // Deliberate: the length byte is a uint8; a wrapped length lets the tail of an attacker-chosen d tag decode as further TLV records, injecting an author — see ADR-0104
    public function testRejectsIdentifierCraftedToInjectAnAuthorRecord(): void
    {
        $crafted = str_repeat('s', 34);
        $crafted .= pack('CC', 2, 32).str_repeat('e', 32);
        $crafted .= pack('CC', 9, 110).str_repeat('x', 110);
        $crafted .= pack('CC', 9, 108).str_repeat('x', 108);

        $this->assertSame(290, strlen($crafted));
        $this->assertSame(34, ord(pack('C', strlen($crafted))));

        $this->assertNull($this->naddr($crafted));
    }

    // Deliberate: the coordinate's own relay hint must reach the encoding; parseEventReference reads the first relay back as the hint, so dropping it here loses it in both directions — see nostr-adrs ADR-0084
    public function testCoordinateRelayHintLeadsTheEncodedRelays(): void
    {
        $naddr = Naddr::tryFromCoordinate($this->coordinate('slug')->withRelayHint($this->hint()));

        $this->assertNotNull($naddr);
        $this->assertSame(['wss://hint.example.com'], $naddr->getRelays()->toStrings());
    }

    public function testCoordinateRelayHintSurvivesTheRoundTrip(): void
    {
        $naddr = Naddr::tryFromCoordinate($this->coordinate('slug')->withRelayHint($this->hint()));
        $this->assertNotNull($naddr);

        $reference = Nip19Codec::parseEventReference($naddr->toBech32());

        $this->assertInstanceOf(EventCoordinate::class, $reference);
        $this->assertSame('wss://hint.example.com', (string) $reference->getRelayHint());
    }

    public function testRelayHintIsNotDuplicatedWhenAlsoSuppliedExplicitly(): void
    {
        $naddr = Naddr::tryFromCoordinate(
            $this->coordinate('slug')->withRelayHint($this->hint()),
            new RelayUrlCollection([$this->hint()]),
        );

        $this->assertNotNull($naddr);
        $this->assertSame(['wss://hint.example.com'], $naddr->getRelays()->toStrings());
    }

    // Deliberate: getRelays() must report what the bytes carry; storing the caller's collection while encoding a different one makes the object disagree with its own encoding — see nostr-adrs ADR-0084
    public function testReportedRelaysMatchTheEncodedRecords(): void
    {
        $naddr = Naddr::tryFromCoordinate($this->coordinate('slug')->withRelayHint($this->hint()));
        $this->assertNotNull($naddr);

        $decoded = Naddr::tryFromBech32($naddr->toBech32());

        $this->assertNotNull($decoded);
        $this->assertSame($naddr->getRelays()->toStrings(), $decoded->getRelays()->toStrings());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function acceptedCoordinates(): iterable
    {
        yield 'replaceable kind with an empty d' => [10002, ''];
        yield 'addressable kind with an empty d' => [30023, ''];
        yield 'addressable kind with a d' => [30023, 'x'];
    }

    // Deliberate: NIP-19 names the special record the d tag and says to use an empty string for a normal replaceable event, so kind and identifier are accepted together by the NIP-01 category rule — see ADR-0082
    #[DataProvider('acceptedCoordinates')]
    public function testDecodesAPayloadWhoseKindAndIdentifierFormACoordinate(int $kind, string $identifier): void
    {
        $decoded = Naddr::tryFromBech32(Bech32Mother::encode(Naddr::HRP, self::special($identifier).self::author().self::kind($kind)));

        $this->assertSame($kind.':'.self::PUBKEY_HEX.':'.$identifier, (string) $decoded?->getCoordinate());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function rejectedCoordinates(): iterable
    {
        yield 'replaceable kind with a non-empty d' => [10002, 'x'];
        yield 'regular kind' => [1, ''];
        yield 'regular kind with a d' => [1, 'x'];
        yield 'ephemeral kind' => [20000, ''];
    }

    #[DataProvider('rejectedCoordinates')]
    public function testRejectsAPayloadWhoseKindAndIdentifierFormNoCoordinate(int $kind, string $identifier): void
    {
        $this->assertNull(Naddr::tryFromBech32(Bech32Mother::encode(Naddr::HRP, self::special($identifier).self::author().self::kind($kind))));
    }

    public function testRejectsAPayloadWithoutAnAuthorRecord(): void
    {
        $this->assertNull(Naddr::tryFromBech32(Bech32Mother::encode(Naddr::HRP, self::special('x').self::kind(30023))));
    }

    public function testRejectsAPayloadWithoutAKindRecord(): void
    {
        $this->assertNull(Naddr::tryFromBech32(Bech32Mother::encode(Naddr::HRP, self::special('x').self::author())));
    }

    public function testIgnoresAnUnknownTlvRecord(): void
    {
        $unknown = pack('CC', 99, 3).'abc';
        $decoded = Naddr::tryFromBech32(Bech32Mother::encode(Naddr::HRP, self::special('x').$unknown.self::author().self::kind(30023)));

        $this->assertSame('30023:'.self::PUBKEY_HEX.':x', (string) $decoded?->getCoordinate());
    }

    public function testEncodesAReplaceableCoordinateWithAnEmptySpecialRecord(): void
    {
        $naddr = Naddr::tryFromCoordinate(EventCoordinate::tryFrom(
            EventKind::fromInt(10002),
            PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey'),
            '',
        ) ?? throw new RuntimeException('Invalid test coordinate'));

        $this->assertSame('10002:'.self::PUBKEY_HEX.':', (string) Naddr::tryFromBech32($naddr?->toBech32() ?? '')?->getCoordinate());
    }

    public function testRefusesAnIdentifierThatIsNotUtf8(): void
    {
        $this->assertNull(Naddr::tryFromBech32('naddr1qqp0llszypumuen7l8wthtz45p3ftn58pvrs9xlumvkuu2xet8egzkcklqtesqcyqqq823cpytqe3'));
    }

    public function testReadsRecordsRepeatedWithOneValueOnce(): void
    {
        $decoded = Naddr::tryFromPayload(self::special('x').self::special('x').self::author().self::author().self::kind(30023).self::kind(30023));

        $this->assertSame('30023:'.self::PUBKEY_HEX.':x', (string) $decoded?->getCoordinate());
    }

    public function testRejectsKindRecordsThatDisagree(): void
    {
        $this->assertNull(Naddr::tryFromPayload(self::special('x').self::author().self::kind(30023).self::kind(30024)));
    }

    public function testRejectsIdentifierRecordsThatDisagree(): void
    {
        $this->assertNull(Naddr::tryFromPayload(self::special('x').self::special('y').self::author().self::kind(30023)));
    }

    private static function special(string $identifier): string
    {
        return pack('CC', 0, strlen($identifier)).$identifier;
    }

    private static function author(): string
    {
        return pack('CC', 2, 32).(hex2bin(self::PUBKEY_HEX) ?: throw new RuntimeException('Invalid test pubkey'));
    }

    private static function kind(int $kind): string
    {
        return pack('CCN', 3, 4, $kind);
    }

    public function testRefusesACoordinateWhoseEncodingWouldPassTheNip19Bound(): void
    {
        $this->assertNull(Naddr::tryFromCoordinate($this->coordinate('my-article'), RelayUrlMother::beyondTheNip19EncodingBound()));
    }

    private function hint(): RelayUrl
    {
        return RelayUrl::fromString('wss://hint.example.com');
    }

    private function coordinate(string $identifier): EventCoordinate
    {
        return EventCoordinate::tryFrom(
            EventKind::fromInt(self::ADDRESSABLE_KIND),
            PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey'),
            $identifier,
        ) ?? throw new RuntimeException('Invalid test coordinate');
    }

    private function naddr(string $identifier): ?Naddr
    {
        $coordinate = EventCoordinate::tryFrom(
            EventKind::fromInt(self::ADDRESSABLE_KIND),
            PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey'),
            $identifier,
        ) ?? throw new RuntimeException('Invalid test coordinate');

        return Naddr::tryFromCoordinate($coordinate);
    }
}
