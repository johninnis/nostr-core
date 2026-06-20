<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use Innis\Nostr\Core\Infrastructure\Crypto\NativeRandomBytesGenerator;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use PHPUnit\Framework\TestCase;

final class EcdhParityComplianceTest extends TestCase
{
    private const ITERATIONS = 100;

    public function testFfiAndPurePhpComputeByteIdenticalSharedXAcrossRandomInputs(): void
    {
        $ffiEcdh = $this->ffiEcdhService();
        $purePhpEcdh = new Secp256k1Ecdh();
        $signer = new Secp256k1Signer(null, new NativeRandomBytesGenerator());

        $problems = [];

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKey = PrivateKey::generate();
            $peerPublicKey = $signer->derivePublicKey(PrivateKey::generate());

            $ffiShared = bin2hex($ffiEcdh->computeSharedX($privateKey, $peerPublicKey));
            $phpShared = bin2hex($purePhpEcdh->computeSharedX($privateKey, $peerPublicKey));

            if ($ffiShared !== $phpShared) {
                $problems[] = sprintf(
                    'iteration %d: FFI %s != pure-PHP %s',
                    $i,
                    $ffiShared,
                    $phpShared,
                );
            }
        }

        $this->assertSame([], $problems, "ECDH shared-X divergences:\n".implode("\n", $problems));
    }

    public function testSharedXIsSymmetricAcrossBothEngines(): void
    {
        $ffiEcdh = $this->ffiEcdhService();
        $purePhpEcdh = new Secp256k1Ecdh();
        $signer = new Secp256k1Signer(null, new NativeRandomBytesGenerator());

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKeyA = PrivateKey::generate();
            $privateKeyB = PrivateKey::generate();
            $publicKeyA = $signer->derivePublicKey($privateKeyA);
            $publicKeyB = $signer->derivePublicKey($privateKeyB);

            $ffiAtoB = $ffiEcdh->computeSharedX($privateKeyA, $publicKeyB);
            $ffiBtoA = $ffiEcdh->computeSharedX($privateKeyB, $publicKeyA);
            $phpAtoB = $purePhpEcdh->computeSharedX($privateKeyA, $publicKeyB);
            $phpBtoA = $purePhpEcdh->computeSharedX($privateKeyB, $publicKeyA);

            $this->assertSame($ffiAtoB, $ffiBtoA, sprintf('iteration %d: FFI asymmetric', $i));
            $this->assertSame($phpAtoB, $phpBtoA, sprintf('iteration %d: pure-PHP asymmetric', $i));
            $this->assertSame($ffiAtoB, $phpAtoB, sprintf('iteration %d: FFI vs pure-PHP diverged', $i));
        }
    }

    private function ffiEcdhService(): Secp256k1Ecdh
    {
        $randomBytes = new NativeRandomBytesGenerator();
        $ffi = LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32))
            ?? self::markTestSkipped('libsecp256k1 FFI unavailable');

        return new Secp256k1Ecdh($ffi);
    }
}
