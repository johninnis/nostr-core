<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use Innis\Nostr\Core\Infrastructure\Crypto\NativeRandomBytesGenerator;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Bip340PropertyComplianceTest extends TestCase
{
    private const ITERATIONS = 100;

    public function testPubkeyDerivationParityAcrossRandomInputs(): void
    {
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKey = PrivateKey::generate();

            $ffiPubkey = $ffiService->derivePublicKey($privateKey)->toHex();
            $phpPubkey = $purePhpService->derivePublicKey($privateKey)->toHex();

            $this->assertSame(
                $ffiPubkey,
                $phpPubkey,
                sprintf('iteration %d: pubkey derivation diverged between FFI and pure-PHP', $i),
            );
        }
    }

    public function testSignatureCrossVerifiesAcrossRandomInputs(): void
    {
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();
        $randomBytes = new NativeRandomBytesGenerator();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKey = PrivateKey::generate();
            $publicKey = $ffiService->derivePublicKey($privateKey);
            $message = $randomBytes->bytes(32);

            $ffiSig = $ffiService->sign($privateKey, $message);
            $phpSig = $purePhpService->sign($privateKey, $message);

            $this->assertTrue(
                $ffiService->verify($publicKey, $message, $ffiSig),
                sprintf('iteration %d: FFI-signed sig failed FFI verify', $i),
            );
            $this->assertTrue(
                $purePhpService->verify($publicKey, $message, $ffiSig),
                sprintf('iteration %d: FFI-signed sig failed pure-PHP verify', $i),
            );
            $this->assertTrue(
                $ffiService->verify($publicKey, $message, $phpSig),
                sprintf('iteration %d: pure-PHP-signed sig failed FFI verify', $i),
            );
            $this->assertTrue(
                $purePhpService->verify($publicKey, $message, $phpSig),
                sprintf('iteration %d: pure-PHP-signed sig failed pure-PHP verify', $i),
            );
        }
    }

    public function testTamperedSignatureRejectedByBothEngines(): void
    {
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();
        $randomBytes = new NativeRandomBytesGenerator();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKey = PrivateKey::generate();
            $publicKey = $ffiService->derivePublicKey($privateKey);
            $message = $randomBytes->bytes(32);

            $sig = $ffiService->sign($privateKey, $message);
            $tampered = $this->flipFirstByteOfSignature($sig->toHex());

            $this->assertFalse(
                $ffiService->verify($publicKey, $message, $tampered),
                sprintf('iteration %d: FFI verifier accepted tampered signature', $i),
            );
            $this->assertFalse(
                $purePhpService->verify($publicKey, $message, $tampered),
                sprintf('iteration %d: pure-PHP verifier accepted tampered signature', $i),
            );
        }
    }

    private function flipFirstByteOfSignature(string $hex): Signature
    {
        $byte = hexdec(substr($hex, 0, 2));
        $flipped = sprintf('%02x', $byte ^ 0xFF).substr($hex, 2);

        return Signature::fromHex($flipped)
            ?? throw new RuntimeException('Failed to build tampered signature');
    }

    private function ffiService(): Secp256k1Signer
    {
        $randomBytes = new NativeRandomBytesGenerator();
        $ffi = LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32))
            ?? self::markTestSkipped('libsecp256k1 FFI unavailable');

        return new Secp256k1Signer($ffi, $randomBytes);
    }

    private function purePhpService(): Secp256k1Signer
    {
        return new Secp256k1Signer(null, new NativeRandomBytesGenerator());
    }
}
