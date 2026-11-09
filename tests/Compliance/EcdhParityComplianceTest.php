<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('ffi')]
final class EcdhParityComplianceTest extends TestCase
{
    private const int ITERATIONS = 100;

    public function testFfiAndPurePhpComputeByteIdenticalSharedXAcrossRandomInputs(): void
    {
        $ffiEcdh = $this->ffiEcdhService();
        $purePhpEcdh = Secp256k1Ecdh::createPurePhp();
        $signer = Secp256k1Signer::createPurePhp();

        $problems = [];

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKey = PrivateKey::generate();
            $peerPublicKey = $signer->derivePublicKey(PrivateKey::generate());

            $ffiShared = bin2hex(self::bytes($ffiEcdh->computeSharedX($privateKey, $peerPublicKey)));
            $phpShared = bin2hex(self::bytes($purePhpEcdh->computeSharedX($privateKey, $peerPublicKey)));

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
        $purePhpEcdh = Secp256k1Ecdh::createPurePhp();
        $signer = Secp256k1Signer::createPurePhp();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKeyA = PrivateKey::generate();
            $privateKeyB = PrivateKey::generate();
            $publicKeyA = $signer->derivePublicKey($privateKeyA);
            $publicKeyB = $signer->derivePublicKey($privateKeyB);

            $ffiAtoB = self::bytes($ffiEcdh->computeSharedX($privateKeyA, $publicKeyB));
            $ffiBtoA = self::bytes($ffiEcdh->computeSharedX($privateKeyB, $publicKeyA));
            $phpAtoB = self::bytes($purePhpEcdh->computeSharedX($privateKeyA, $publicKeyB));
            $phpBtoA = self::bytes($purePhpEcdh->computeSharedX($privateKeyB, $publicKeyA));

            $this->assertSame($ffiAtoB, $ffiBtoA, sprintf('iteration %d: FFI asymmetric', $i));
            $this->assertSame($phpAtoB, $phpBtoA, sprintf('iteration %d: pure-PHP asymmetric', $i));
            $this->assertSame($ffiAtoB, $phpAtoB, sprintf('iteration %d: FFI vs pure-PHP diverged', $i));
        }
    }

    private function ffiEcdhService(): Secp256k1Ecdh
    {
        return Secp256k1Ecdh::create();
    }

    private static function bytes(SecretKeyMaterial $sharedX): string
    {
        return $sharedX->expose(static fn (string $bytes): string => $bytes);
    }
}
