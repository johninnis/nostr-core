<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class Secp256k1EcdhTest extends TestCase
{
    private const string OFF_CURVE_X_HEX = '0000000000000000000000000000000000000000000000000000000000000005';
    private const string ZERO_X_HEX = '0000000000000000000000000000000000000000000000000000000000000000';
    private const string FIELD_PRIME_X_HEX = 'fffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f';
    private const int REPEATED_AGREEMENT_COUNT = 2000;
    private const int REPEATED_AGREEMENT_MEMORY_BOUND_BYTES = 65536;

    #[Group('ffi')]
    public function testRepeatedNativeKeyAgreementKeepsMemoryFlat(): void
    {
        $ecdh = Secp256k1Ecdh::create();
        $signer = Secp256k1Signer::create();
        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());
        $ecdh->computeSharedX($privateKey, $otherPublicKey);

        $memoryBefore = memory_get_usage();
        for ($agreement = 0; $agreement < self::REPEATED_AGREEMENT_COUNT; ++$agreement) {
            $ecdh->computeSharedX($privateKey, $otherPublicKey);
        }

        $this->assertLessThan(self::REPEATED_AGREEMENT_MEMORY_BOUND_BYTES, memory_get_usage() - $memoryBefore);
    }

    public function testComputeSharedXIsSymmetric(): void
    {
        $ecdh = Secp256k1Ecdh::createPurePhp();
        $signer = Secp256k1Signer::createPurePhp();

        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyA = $signer->derivePublicKey($privateKeyA);
        $publicKeyB = $signer->derivePublicKey($privateKeyB);

        $aToB = self::bytes($ecdh->computeSharedX($privateKeyA, $publicKeyB));
        $bToA = self::bytes($ecdh->computeSharedX($privateKeyB, $publicKeyA));

        $this->assertSame($aToB, $bToA);
    }

    public function testComputeSharedXIsDeterministic(): void
    {
        $ecdh = Secp256k1Ecdh::createPurePhp();
        $signer = Secp256k1Signer::createPurePhp();

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $first = self::bytes($ecdh->computeSharedX($privateKey, $otherPublicKey));
        $second = self::bytes($ecdh->computeSharedX($privateKey, $otherPublicKey));

        $this->assertSame($first, $second);
    }

    public function testComputeSharedXMatchesLegacyConversationKeyDerivation(): void
    {
        $ecdh = Secp256k1Ecdh::createPurePhp();
        $signer = Secp256k1Signer::createPurePhp();

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $sharedX = self::bytes($ecdh->computeSharedX($privateKey, $otherPublicKey));
        $expectedConversationKey = hash_hmac('sha256', $sharedX, 'nip44-v2', true);

        $legacyKey = ConversationKey::derive($privateKey, $otherPublicKey, $ecdh);
        $legacyBytes = $legacyKey->expose(static fn (string $bytes): string => $bytes);

        $this->assertSame($expectedConversationKey, $legacyBytes);
    }

    public function testComputeSharedXProducesThirtyTwoBytes(): void
    {
        $ecdh = Secp256k1Ecdh::createPurePhp();
        $signer = Secp256k1Signer::createPurePhp();

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $sharedX = self::bytes($ecdh->computeSharedX($privateKey, $otherPublicKey));

        $this->assertSame(32, strlen($sharedX));
    }

    public function testComputeSharedXRejectsInFieldOffCurvePublicKeyOnPurePhpPath(): void
    {
        $ecdh = Secp256k1Ecdh::createPurePhp();
        $offCurveKey = PublicKey::tryFromHex(self::OFF_CURVE_X_HEX);
        $this->assertNotNull($offCurveKey);

        $this->expectException(EcdhException::class);
        $ecdh->computeSharedX(PrivateKey::generate(), $offCurveKey);
    }

    #[Group('ffi')]
    public function testComputeSharedXRejectsInFieldOffCurvePublicKeyOnNativePath(): void
    {
        $ecdh = Secp256k1Ecdh::create();
        $offCurveKey = PublicKey::tryFromHex(self::OFF_CURVE_X_HEX);
        $this->assertNotNull($offCurveKey);

        $this->expectException(EcdhException::class);
        $ecdh->computeSharedX(PrivateKey::generate(), $offCurveKey);
    }

    public function testComputeSharedXRejectsZeroXCoordinateOnPurePhpPath(): void
    {
        $this->assertFieldRangeRejection(Secp256k1Ecdh::createPurePhp(), self::ZERO_X_HEX);
    }

    #[Group('ffi')]
    public function testComputeSharedXRejectsZeroXCoordinateOnNativePath(): void
    {
        $this->assertFieldRangeRejection(Secp256k1Ecdh::create(), self::ZERO_X_HEX);
    }

    public function testComputeSharedXRejectsXCoordinateAtFieldPrimeOnPurePhpPath(): void
    {
        $this->assertFieldRangeRejection(Secp256k1Ecdh::createPurePhp(), self::FIELD_PRIME_X_HEX);
    }

    #[Group('ffi')]
    public function testComputeSharedXRejectsXCoordinateAtFieldPrimeOnNativePath(): void
    {
        $this->assertFieldRangeRejection(Secp256k1Ecdh::create(), self::FIELD_PRIME_X_HEX);
    }

    private function assertFieldRangeRejection(Secp256k1Ecdh $ecdh, string $xHex): void
    {
        $outOfRangeKey = PublicKey::tryFromHex($xHex);
        $this->assertNotNull($outOfRangeKey);

        $this->expectException(EcdhException::class);
        $this->expectExceptionMessage('out of field range');
        $ecdh->computeSharedX(PrivateKey::generate(), $outOfRangeKey);
    }

    private static function bytes(SecretKeyMaterial $sharedX): string
    {
        return $sharedX->expose(static fn (string $bytes): string => $bytes);
    }
}
