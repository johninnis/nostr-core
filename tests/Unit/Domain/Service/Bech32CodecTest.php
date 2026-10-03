<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Bech32Variant;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Tests\Support\Bech32Mother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Bech32CodecTest extends TestCase
{
    public function testDecodeRejectsValidChecksumWithNonCanonicalPadding(): void
    {
        $this->assertNull(Bech32Codec::decode('npub1pc3jnnt'));
    }

    public function testDecodeRejectsValidChecksumOverAnInvalidDataCharacter(): void
    {
        $this->assertNull(Bech32Codec::decode('npub1brfkf0u'));
    }

    public function testDefaultVariantIsBech32(): void
    {
        $payload = "\x00\x01\x02\x03";

        $encodedDefault = Bech32Mother::encode('test', $payload);
        $encodedExplicit = Bech32Mother::encode('test', $payload, Bech32Variant::Bech32);

        $this->assertSame($encodedExplicit, $encodedDefault);

        $decodedDefault = Bech32Codec::decode($encodedDefault) ?? throw new RuntimeException('expected valid decode');
        $this->assertSame('test', $decodedDefault['hrp']);
        $this->assertSame($payload, $decodedDefault['data']);
    }

    public function testBech32mRoundTrip(): void
    {
        $payload = "\xDE\xAD\xBE\xEF\xCA\xFE";

        $encoded = Bech32Mother::encode('bfshare', $payload, Bech32Variant::Bech32m);
        $decoded = Bech32Codec::decode($encoded, Bech32Variant::Bech32m) ?? throw new RuntimeException('expected valid decode');

        $this->assertSame('bfshare', $decoded['hrp']);
        $this->assertSame($payload, $decoded['data']);
    }

    public function testBech32mEncodingDiffersFromBech32(): void
    {
        $payload = "\x00\x01\x02\x03";

        $bech32 = Bech32Mother::encode('test', $payload, Bech32Variant::Bech32);
        $bech32m = Bech32Mother::encode('test', $payload, Bech32Variant::Bech32m);

        $this->assertNotSame($bech32, $bech32m);
    }

    public function testBech32mStringRejectedWhenDecodedAsBech32(): void
    {
        $encoded = Bech32Mother::encode('test', "\x00\x01", Bech32Variant::Bech32m);

        $this->assertNull(Bech32Codec::decode($encoded, Bech32Variant::Bech32));
    }

    public function testBech32StringRejectedWhenDecodedAsBech32m(): void
    {
        $encoded = Bech32Mother::encode('test', "\x00\x01", Bech32Variant::Bech32);

        $this->assertNull(Bech32Codec::decode($encoded, Bech32Variant::Bech32m));
    }

    public function testCorruptedBech32mChecksumRejected(): void
    {
        $encoded = Bech32Mother::encode('test', "\x00\x01\x02", Bech32Variant::Bech32m);
        $corrupted = substr($encoded, 0, -1).self::flipLastChar($encoded);

        $this->assertNull(Bech32Codec::decode($corrupted, Bech32Variant::Bech32m));
    }

    public function testBip350EmptyDataVector(): void
    {
        $decoded = Bech32Codec::decode('?1v759aa', Bech32Variant::Bech32m) ?? throw new RuntimeException('expected valid decode');

        $this->assertSame('?', $decoded['hrp']);
        $this->assertSame('', $decoded['data']);
    }

    #[DataProvider('variantChecksumConstants')]
    public function testVariantValueMatchesSpecConstant(Bech32Variant $variant, int $checksumConstant): void
    {
        $this->assertSame($checksumConstant, $variant->value);
    }

    /**
     * @return iterable<string, array{Bech32Variant, int}>
     */
    public static function variantChecksumConstants(): iterable
    {
        yield 'bech32 (BIP-173)' => [Bech32Variant::Bech32, 1];
        yield 'bech32m (BIP-350)' => [Bech32Variant::Bech32m, 0x2BC830A3];
    }

    private static function flipLastChar(string $encoded): string
    {
        $last = $encoded[strlen($encoded) - 1];
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $position = strpos($charset, $last);
        if (false === $position) {
            return 'q';
        }

        return $charset[($position + 1) % strlen($charset)];
    }

    public function testEncodesAStringOfExactlyTheNip19Bound(): void
    {
        $this->assertSame(5000, strlen(Bech32Codec::encode('test', str_repeat("\x00", 3118)) ?? ''));
    }

    public function testRefusesToEncodeAStringBeyondTheNip19Bound(): void
    {
        $this->assertNull(Bech32Codec::encode('test', str_repeat("\x00", 3119)));
    }

    #[DataProvider('prefixesTheDecoderRefuses')]
    public function testRefusesToEncodeAPrefixTheDecoderRefuses(string $hrp): void
    {
        $this->assertNull(Bech32Codec::encode($hrp, "\x00\x01\x02"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function prefixesTheDecoderRefuses(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['NPUB'];
        yield 'mixed case' => ['nPub'];
        yield 'a space' => ['n pub'];
        yield 'a control character' => ["n\x7fpub"];
        yield 'a byte above ASCII' => ["n\xc3\xa9"];
        yield 'longer than 83 characters' => [str_repeat('a', 84)];
    }

    public function testEncodesAPrefixOfEightyThreeCharacters(): void
    {
        $encoded = Bech32Codec::encode(str_repeat('a', 83), "\x00\x01\x02") ?? self::fail('An 83-character prefix did not encode');

        $this->assertSame(str_repeat('a', 83), Bech32Codec::decode($encoded)['hrp'] ?? null);
    }

    public function testDecodeReadsAPrefixOfEightyThreeCharacters(): void
    {
        $this->assertNotNull(Bech32Codec::decode(str_repeat('a', 83).'1qqqqqesa09k'));
    }

    public function testDecodeRefusesAPrefixLongerThanEightyThreeCharacters(): void
    {
        $this->assertNull(Bech32Codec::decode(str_repeat('a', 84).'1qqqqqjvtpnj'));
    }

    public function testEveryPrefixItEncodesItDecodes(): void
    {
        $encoded = Bech32Codec::encode('!~1', "\x00\x01\x02") ?? self::fail('A printable prefix did not encode');

        $this->assertSame('!~1', Bech32Codec::decode($encoded)['hrp'] ?? null);
    }
}
