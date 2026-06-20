<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Bech32Variant;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Bech32CodecTest extends TestCase
{
    public function testDefaultVariantIsBech32(): void
    {
        $payload = [0x00, 0x01, 0x02, 0x03];

        $encodedDefault = Bech32Codec::encode('test', $payload);
        $encodedExplicit = Bech32Codec::encode('test', $payload, Bech32Variant::Bech32);

        $this->assertSame($encodedExplicit, $encodedDefault);

        $decodedDefault = Bech32Codec::decode($encodedDefault) ?? throw new RuntimeException('expected valid decode');
        $this->assertSame('test', $decodedDefault['hrp']);
        $this->assertSame($payload, $decodedDefault['data']);
    }

    public function testBech32mRoundTrip(): void
    {
        $payload = [0xDE, 0xAD, 0xBE, 0xEF, 0xCA, 0xFE];

        $encoded = Bech32Codec::encode('bfshare', $payload, Bech32Variant::Bech32m);
        $decoded = Bech32Codec::decode($encoded, Bech32Variant::Bech32m) ?? throw new RuntimeException('expected valid decode');

        $this->assertSame('bfshare', $decoded['hrp']);
        $this->assertSame($payload, $decoded['data']);
    }

    public function testBech32mEncodingDiffersFromBech32(): void
    {
        $payload = [0x00, 0x01, 0x02, 0x03];

        $bech32 = Bech32Codec::encode('test', $payload, Bech32Variant::Bech32);
        $bech32m = Bech32Codec::encode('test', $payload, Bech32Variant::Bech32m);

        $this->assertNotSame($bech32, $bech32m);
    }

    public function testBech32mStringRejectedWhenDecodedAsBech32(): void
    {
        $encoded = Bech32Codec::encode('test', [0x00, 0x01], Bech32Variant::Bech32m);

        $this->assertNull(Bech32Codec::decode($encoded, Bech32Variant::Bech32));
    }

    public function testBech32StringRejectedWhenDecodedAsBech32m(): void
    {
        $encoded = Bech32Codec::encode('test', [0x00, 0x01], Bech32Variant::Bech32);

        $this->assertNull(Bech32Codec::decode($encoded, Bech32Variant::Bech32m));
    }

    public function testCorruptedBech32mChecksumRejected(): void
    {
        $encoded = Bech32Codec::encode('test', [0x00, 0x01, 0x02], Bech32Variant::Bech32m);
        $corrupted = substr($encoded, 0, -1).self::flipLastChar($encoded);

        $this->assertNull(Bech32Codec::decode($corrupted, Bech32Variant::Bech32m));
    }

    public function testBip350EmptyDataVector(): void
    {
        $decoded = Bech32Codec::decode('?1v759aa', Bech32Variant::Bech32m) ?? throw new RuntimeException('expected valid decode');

        $this->assertSame('?', $decoded['hrp']);
        $this->assertSame([], $decoded['data']);
    }

    public function testBech32mVariantValueMatchesBip350Constant(): void
    {
        $this->assertSame(0x2BC830A3, Bech32Variant::Bech32m->value);
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
