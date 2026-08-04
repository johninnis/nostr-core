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

    // Deliberate: the length byte is a uint8; a wrapped length lets the tail of an attacker-chosen d tag decode as further TLV records, injecting an author — see ADR-0060
    public function testRejectsIdentifierCraftedToInjectAnAuthorRecord(): void
    {
        $crafted = str_repeat('s', 34);
        $crafted .= pack('CC', 2, 32).str_repeat("\xee", 32);
        $padding = 290 - strlen($crafted) - 2;
        $crafted .= pack('CC', 9, $padding).str_repeat('x', $padding);

        $this->assertSame(290, strlen($crafted));
        $this->assertSame(34, ord(pack('C', strlen($crafted))));

        $this->assertNull($this->naddr($crafted));
    }

    // Deliberate: the coordinate's own relay hint must reach the encoding; parseEventReference reads the first relay back as the hint, so dropping it here loses it in both directions — see ADR-0060
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

        $reference = new Nip19Codec()->parseEventReference($naddr->toBech32());

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

    // Deliberate: getRelays() must report what the bytes carry; storing the caller's collection while encoding a different one makes the object disagree with its own encoding — see ADR-0060
    public function testReportedRelaysMatchTheEncodedRecords(): void
    {
        $naddr = Naddr::tryFromCoordinate($this->coordinate('slug')->withRelayHint($this->hint()));
        $this->assertNotNull($naddr);

        $decoded = Naddr::tryFromBech32($naddr->toBech32());

        $this->assertNotNull($decoded);
        $this->assertSame($naddr->getRelays()->toStrings(), $decoded->getRelays()->toStrings());
    }

    private function hint(): RelayUrl
    {
        return RelayUrl::tryFromString('wss://hint.example.com') ?? throw new RuntimeException('Invalid test relay');
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
