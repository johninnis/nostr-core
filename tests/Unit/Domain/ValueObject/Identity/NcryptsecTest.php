<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Tests\Support\Bech32Mother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NcryptsecTest extends TestCase
{
    private const string SPEC_VECTOR_NCRYPTSEC = 'ncryptsec1qgg9947rlpvqu76pj5ecreduf9jxhselq2nae2kghhvd5g7dgjtcxfqtd67p9m0w57lspw8gsq6yphnm8623nsl8xn9j4jdzz84zm3frztj3z7s35vpzmqf6ksu8r89qk5z2zxfmu5gv8th8wclt0h4p';

    public function testTryFromStringAcceptsValidNcryptsec(): void
    {
        $this->assertNotNull(Ncryptsec::tryFromString(self::SPEC_VECTOR_NCRYPTSEC));
    }

    public function testTryFromStringRejectsWrongPayloadLength(): void
    {
        $this->assertNull(Ncryptsec::tryFromString(Bech32Mother::encode(Ncryptsec::HRP, str_repeat("\0", 10))));
    }

    public function testTryFromStringRejectsWrongVersionByte(): void
    {
        $wrongVersion = chr(0x01).str_repeat("\0", Ncryptsec::PAYLOAD_LENGTH - 1);

        $this->assertNull(Ncryptsec::tryFromString(Bech32Mother::encode(Ncryptsec::HRP, $wrongVersion)));
    }

    public function testTryFromStringRefusesAnUnknownKeySecurityByte(): void
    {
        $this->assertNull(Ncryptsec::tryFromString($this->withPayloadByte(self::SPEC_VECTOR_NCRYPTSEC, 42, 0x03)));
    }

    public function testReadsTheKeySecurityByteItCarries(): void
    {
        $ncryptsec = Ncryptsec::tryFromString(self::SPEC_VECTOR_NCRYPTSEC);

        $this->assertSame(KeySecurityByte::KnownInsecure, $ncryptsec?->getKeySecurity());
    }

    public function testCreateCarriesTheKeySecurityItWasGiven(): void
    {
        $ncryptsec = Ncryptsec::create(16, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::NotKnownInsecure, str_repeat('a', 48));

        $this->assertSame(KeySecurityByte::NotKnownInsecure, $ncryptsec->getKeySecurity());
    }

    public function testWritesAnUpperCaseInputInItsCanonicalLowerCaseForm(): void
    {
        $ncryptsec = Ncryptsec::tryFromString(strtoupper(self::SPEC_VECTOR_NCRYPTSEC));

        $this->assertSame(self::SPEC_VECTOR_NCRYPTSEC, (string) $ncryptsec);
    }

    public function testTryFromReadsTheFieldsItWasGiven(): void
    {
        $ncryptsec = Ncryptsec::tryFrom(16, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::Untracked, str_repeat('a', 48));

        $this->assertSame(
            [16, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::Untracked, str_repeat('a', 48)],
            [$ncryptsec?->getLogN(), $ncryptsec?->getSalt(), $ncryptsec?->getNonce(), $ncryptsec?->getKeySecurity(), $ncryptsec?->getAeadCiphertextAndTag()],
        );
    }

    #[DataProvider('invalidFields')]
    public function testTryFromRefusesAFieldOutsideItsRule(int $logN, string $salt, string $nonce, string $aeadOutput): void
    {
        $this->assertNull(Ncryptsec::tryFrom($logN, $salt, $nonce, KeySecurityByte::Untracked, $aeadOutput));
    }

    /**
     * @return iterable<string, array{int, string, string, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'negative logN' => [-1, str_repeat('s', 16), str_repeat('n', 24), str_repeat('a', 48)];
        yield 'logN over one byte' => [256, str_repeat('s', 16), str_repeat('n', 24), str_repeat('a', 48)];
        yield 'short salt' => [16, str_repeat('s', 15), str_repeat('n', 24), str_repeat('a', 48)];
        yield 'long nonce' => [16, str_repeat('s', 16), str_repeat('n', 25), str_repeat('a', 48)];
        yield 'short salt and long nonce of the right total' => [16, str_repeat('s', 15), str_repeat('n', 25), str_repeat('a', 48)];
        yield 'short AEAD output' => [16, str_repeat('s', 16), str_repeat('n', 24), str_repeat('a', 47)];
    }

    public function testFromFieldsRejectsOutOfRangeLogN(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(256, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::NotKnownInsecure, str_repeat('a', 48));
    }

    public function testFromFieldsRejectsWrongSaltLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(16, str_repeat('s', 15), str_repeat('n', 24), KeySecurityByte::NotKnownInsecure, str_repeat('a', 48));
    }

    public function testFromFieldsRejectsWrongNonceLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(16, str_repeat('s', 16), str_repeat('n', 23), KeySecurityByte::NotKnownInsecure, str_repeat('a', 48));
    }

    public function testFromFieldsRejectsWrongAeadLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ncryptsec::create(16, str_repeat('s', 16), str_repeat('n', 24), KeySecurityByte::NotKnownInsecure, str_repeat('a', 47));
    }

    public function testTryFromStringRejectsWrongHrpNsec(): void
    {
        $this->assertNull(Ncryptsec::tryFromString('nsec1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq2kdzv5'));
    }

    public function testTryFromStringRejectsWrongHrpNpub(): void
    {
        $this->assertNull(Ncryptsec::tryFromString('npub1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqwvfd7z'));
    }

    public function testTryFromStringRejectsInvalidChecksum(): void
    {
        $tampered = substr(self::SPEC_VECTOR_NCRYPTSEC, 0, -6).'xxxxxx';

        $this->assertNull(Ncryptsec::tryFromString($tampered));
    }

    public function testTryFromStringRejectsGarbage(): void
    {
        $this->assertNull(Ncryptsec::tryFromString('not-a-bech32-string'));
    }

    public function testToStringRoundTrips(): void
    {
        $ncryptsec = Ncryptsec::tryFromString(self::SPEC_VECTOR_NCRYPTSEC);

        $this->assertNotNull($ncryptsec);
        $this->assertSame(self::SPEC_VECTOR_NCRYPTSEC, (string) $ncryptsec);
    }

    /**
     * @param int<0, 255> $value
     */
    private function withPayloadByte(string $ncryptsec, int $offset, int $value): string
    {
        $payload = Bech32Codec::decodeWithHrp($ncryptsec, Ncryptsec::HRP) ?? '';
        $payload[$offset] = chr($value);

        return Bech32Mother::encode(Ncryptsec::HRP, $payload);
    }
}
