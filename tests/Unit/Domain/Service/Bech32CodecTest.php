<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Bech32Variant;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
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

        $encodedDefault = Bech32Codec::encode('test', $payload);
        $encodedExplicit = Bech32Codec::encode('test', $payload, Bech32Variant::Bech32);

        $this->assertSame($encodedExplicit, $encodedDefault);

        $decodedDefault = Bech32Codec::decode($encodedDefault) ?? throw new RuntimeException('expected valid decode');
        $this->assertSame('test', $decodedDefault['hrp']);
        $this->assertSame($payload, $decodedDefault['data']);
    }

    public function testBech32mRoundTrip(): void
    {
        $payload = "\xDE\xAD\xBE\xEF\xCA\xFE";

        $encoded = Bech32Codec::encode('bfshare', $payload, Bech32Variant::Bech32m);
        $decoded = Bech32Codec::decode($encoded, Bech32Variant::Bech32m) ?? throw new RuntimeException('expected valid decode');

        $this->assertSame('bfshare', $decoded['hrp']);
        $this->assertSame($payload, $decoded['data']);
    }

    public function testBech32mEncodingDiffersFromBech32(): void
    {
        $payload = "\x00\x01\x02\x03";

        $bech32 = Bech32Codec::encode('test', $payload, Bech32Variant::Bech32);
        $bech32m = Bech32Codec::encode('test', $payload, Bech32Variant::Bech32m);

        $this->assertNotSame($bech32, $bech32m);
    }

    public function testBech32mStringRejectedWhenDecodedAsBech32(): void
    {
        $encoded = Bech32Codec::encode('test', "\x00\x01", Bech32Variant::Bech32m);

        $this->assertNull(Bech32Codec::decode($encoded, Bech32Variant::Bech32));
    }

    public function testBech32StringRejectedWhenDecodedAsBech32m(): void
    {
        $encoded = Bech32Codec::encode('test', "\x00\x01", Bech32Variant::Bech32);

        $this->assertNull(Bech32Codec::decode($encoded, Bech32Variant::Bech32m));
    }

    public function testCorruptedBech32mChecksumRejected(): void
    {
        $encoded = Bech32Codec::encode('test', "\x00\x01\x02", Bech32Variant::Bech32m);
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
}
