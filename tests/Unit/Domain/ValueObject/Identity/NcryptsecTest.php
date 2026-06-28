<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NcryptsecTest extends TestCase
{
    private const SPEC_VECTOR_NCRYPTSEC = 'ncryptsec1qgg9947rlpvqu76pj5ecreduf9jxhselq2nae2kghhvd5g7dgjtcxfqtd67p9m0w57lspw8gsq6yphnm8623nsl8xn9j4jdzz84zm3frztj3z7s35vpzmqf6ksu8r89qk5z2zxfmu5gv8th8wclt0h4p';

    public function testFromStringAcceptsValidNcryptsec(): void
    {
        $this->assertNotNull(Ncryptsec::fromString(self::SPEC_VECTOR_NCRYPTSEC));
    }

    public function testFromStringRejectsWrongPayloadLength(): void
    {
        $this->assertNull(Ncryptsec::fromString(Bech32Codec::encode(Ncryptsec::HRP, str_repeat("\0", 10))));
    }

    public function testFromStringRejectsWrongVersionByte(): void
    {
        $wrongVersion = chr(0x01).str_repeat("\0", Ncryptsec::PAYLOAD_LENGTH - 1);

        $this->assertNull(Ncryptsec::fromString(Bech32Codec::encode(Ncryptsec::HRP, $wrongVersion)));
    }

    public function testFromFieldsRejectsOutOfRangeLogN(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(256, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::ClientSideOnly, str_repeat('a', 48));
    }

    public function testFromFieldsRejectsWrongSaltLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(16, str_repeat('s', 15), str_repeat('n', 24), KeySecurityByte::ClientSideOnly, str_repeat('a', 48));
    }

    public function testFromFieldsRejectsWrongNonceLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(16, str_repeat('s', 16), str_repeat('n', 23), KeySecurityByte::ClientSideOnly, str_repeat('a', 48));
    }

    public function testFromFieldsRejectsWrongAeadLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(16, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::ClientSideOnly, str_repeat('a', 47));
    }

    public function testFromStringRejectsWrongHrpNsec(): void
    {
        $this->assertNull(Ncryptsec::fromString('nsec1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq2kdzv5'));
    }

    public function testFromStringRejectsWrongHrpNpub(): void
    {
        $this->assertNull(Ncryptsec::fromString('npub1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqwvfd7z'));
    }

    public function testFromStringRejectsInvalidChecksum(): void
    {
        $tampered = substr(self::SPEC_VECTOR_NCRYPTSEC, 0, -6).'xxxxxx';

        $this->assertNull(Ncryptsec::fromString($tampered));
    }

    public function testFromStringRejectsGarbage(): void
    {
        $this->assertNull(Ncryptsec::fromString('not-a-bech32-string'));
    }

    public function testToStringRoundTrips(): void
    {
        $ncryptsec = Ncryptsec::fromString(self::SPEC_VECTOR_NCRYPTSEC);

        $this->assertNotNull($ncryptsec);
        $this->assertSame(self::SPEC_VECTOR_NCRYPTSEC, (string) $ncryptsec);
    }
}
