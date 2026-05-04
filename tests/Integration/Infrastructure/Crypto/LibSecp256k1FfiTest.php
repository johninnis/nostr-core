<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use PHPUnit\Framework\TestCase;

final class LibSecp256k1FfiTest extends TestCase
{
    private LibSecp256k1Ffi $ffi;

    protected function setUp(): void
    {
        $ffi = LibSecp256k1Ffi::tryLoad(random_bytes(32));
        if (null === $ffi) {
            $this->markTestSkipped('libsecp256k1 not installed');
        }
        $this->ffi = $ffi;
    }

    public function testDerivePublicKeyCompressedReturns33ByteEncoding(): void
    {
        $secret = str_pad("\x01", 32, "\x00", STR_PAD_LEFT);
        $generator = $this->ffi->derivePublicKeyCompressed($secret);

        $this->assertSame(33, strlen($generator));
        $this->assertSame(
            '0279be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798',
            bin2hex($generator),
        );
    }

    public function testPointAddCompressedSatisfiesGroupLaw(): void
    {
        $g = $this->ffi->derivePublicKeyCompressed(str_pad("\x01", 32, "\x00", STR_PAD_LEFT));
        $twoG = $this->ffi->derivePublicKeyCompressed(str_pad("\x02", 32, "\x00", STR_PAD_LEFT));
        $threeG = $this->ffi->derivePublicKeyCompressed(str_pad("\x03", 32, "\x00", STR_PAD_LEFT));

        $this->assertSame(bin2hex($twoG), bin2hex($this->ffi->pointAddCompressed($g, $g)));
        $this->assertSame(bin2hex($threeG), bin2hex($this->ffi->pointAddCompressed($g, $twoG)));
    }

    public function testPointMulCompressedAgreesWithRepeatedAddition(): void
    {
        $g = $this->ffi->derivePublicKeyCompressed(str_pad("\x01", 32, "\x00", STR_PAD_LEFT));
        $two = str_pad("\x02", 32, "\x00", STR_PAD_LEFT);
        $three = str_pad("\x03", 32, "\x00", STR_PAD_LEFT);

        $this->assertSame(
            bin2hex($this->ffi->pointAddCompressed($g, $g)),
            bin2hex($this->ffi->pointMulCompressed($g, $two)),
        );
        $this->assertSame(
            bin2hex($this->ffi->pointAddCompressed($g, $this->ffi->pointAddCompressed($g, $g))),
            bin2hex($this->ffi->pointMulCompressed($g, $three)),
        );
    }
}
