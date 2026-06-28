<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Crypto\NativeRandomBytesGenerator;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use PHPUnit\Framework\TestCase;

final class Secp256k1EcdhTest extends TestCase
{
    private const string OFF_CURVE_X_HEX = '0000000000000000000000000000000000000000000000000000000000000005';
    private const string ZERO_X_HEX = '0000000000000000000000000000000000000000000000000000000000000000';
    private const string FIELD_PRIME_X_HEX = 'fffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f';

    public function testComputeSharedXIsSymmetric(): void
    {
        $ecdh = new Secp256k1Ecdh(null);
        $signer = new Secp256k1Signer(null, new NativeRandomBytesGenerator());

        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyA = $signer->derivePublicKey($privateKeyA);
        $publicKeyB = $signer->derivePublicKey($privateKeyB);

        $aToB = $ecdh->computeSharedX($privateKeyA, $publicKeyB);
        $bToA = $ecdh->computeSharedX($privateKeyB, $publicKeyA);

        $this->assertSame($aToB, $bToA);
    }

    public function testComputeSharedXIsDeterministic(): void
    {
        $ecdh = new Secp256k1Ecdh(null);
        $signer = new Secp256k1Signer(null, new NativeRandomBytesGenerator());

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $first = $ecdh->computeSharedX($privateKey, $otherPublicKey);
        $second = $ecdh->computeSharedX($privateKey, $otherPublicKey);

        $this->assertSame($first, $second);
    }

    public function testComputeSharedXMatchesLegacyConversationKeyDerivation(): void
    {
        $ecdh = new Secp256k1Ecdh(null);
        $signer = new Secp256k1Signer(null, new NativeRandomBytesGenerator());

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $sharedX = $ecdh->computeSharedX($privateKey, $otherPublicKey);
        $expectedConversationKey = hash_hmac('sha256', $sharedX, 'nip44-v2', true);

        $legacyKey = ConversationKey::derive($privateKey, $otherPublicKey, $ecdh);
        $legacyBytes = $legacyKey->expose(static fn (string $bytes): string => $bytes);

        $this->assertSame($expectedConversationKey, $legacyBytes);
    }

    public function testComputeSharedXProducesThirtyTwoBytes(): void
    {
        $ecdh = new Secp256k1Ecdh(null);
        $signer = new Secp256k1Signer(null, new NativeRandomBytesGenerator());

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $sharedX = $ecdh->computeSharedX($privateKey, $otherPublicKey);

        $this->assertSame(32, strlen($sharedX));
    }

    public function testComputeSharedXRejectsInFieldOffCurvePublicKeyOnPurePhpPath(): void
    {
        $ecdh = new Secp256k1Ecdh(null);
        $offCurveKey = PublicKey::fromHex(self::OFF_CURVE_X_HEX);
        $this->assertNotNull($offCurveKey);

        $this->expectException(EcdhException::class);
        $ecdh->computeSharedX(PrivateKey::generate(), $offCurveKey);
    }

    public function testComputeSharedXRejectsInFieldOffCurvePublicKeyOnDefaultPath(): void
    {
        $ecdh = Secp256k1Ecdh::create();
        $offCurveKey = PublicKey::fromHex(self::OFF_CURVE_X_HEX);
        $this->assertNotNull($offCurveKey);

        $this->expectException(EcdhException::class);
        $ecdh->computeSharedX(PrivateKey::generate(), $offCurveKey);
    }

    public function testComputeSharedXRejectsZeroXCoordinateOnPurePhpPath(): void
    {
        $this->assertFieldRangeRejection(new Secp256k1Ecdh(null), self::ZERO_X_HEX);
    }

    public function testComputeSharedXRejectsZeroXCoordinateOnDefaultPath(): void
    {
        $this->assertFieldRangeRejection(Secp256k1Ecdh::create(), self::ZERO_X_HEX);
    }

    public function testComputeSharedXRejectsXCoordinateAtFieldPrimeOnPurePhpPath(): void
    {
        $this->assertFieldRangeRejection(new Secp256k1Ecdh(null), self::FIELD_PRIME_X_HEX);
    }

    public function testComputeSharedXRejectsXCoordinateAtFieldPrimeOnDefaultPath(): void
    {
        $this->assertFieldRangeRejection(Secp256k1Ecdh::create(), self::FIELD_PRIME_X_HEX);
    }

    private function assertFieldRangeRejection(Secp256k1Ecdh $ecdh, string $xHex): void
    {
        $outOfRangeKey = PublicKey::fromHex($xHex);
        $this->assertNotNull($outOfRangeKey);

        $this->expectException(EcdhException::class);
        $this->expectExceptionMessage('out of field range');
        $ecdh->computeSharedX(PrivateKey::generate(), $outOfRangeKey);
    }
}
