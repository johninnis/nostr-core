<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConversationKeyTest extends TestCase
{
    public function testDerivingWithAPeerKeyTheKeyAgreementRefusesIsAnEncryptionFailure(): void
    {
        $this->expectException(EncryptionException::class);

        ConversationKey::derive(self::ownKey(), self::offCurveKey(), CryptoFixtures::ecdh());
    }

    public function testARefusedPeerKeyKeepsTheKeyAgreementsRefusalAsItsCause(): void
    {
        $cause = null;

        try {
            ConversationKey::derive(self::ownKey(), self::offCurveKey(), CryptoFixtures::ecdh());
        } catch (EncryptionException $failure) {
            $cause = $failure->getPrevious();
        }

        $this->assertInstanceOf(EcdhException::class, $cause);
    }

    public function testARefusedPeerKeyReportsTheKeyAgreementsMessage(): void
    {
        $this->expectExceptionMessage('ECDH public key is not a valid curve point');

        ConversationKey::derive(self::ownKey(), self::offCurveKey(), new Secp256k1Ecdh(null));
    }

    public function testTryFromHexAcceptsValidHex(): void
    {
        $key = ConversationKey::tryFromHex(str_repeat('ab', 32));

        $this->assertNotNull($key);
    }

    public function testTryFromHexReturnsNullForInvalidCharacters(): void
    {
        $this->assertNull(ConversationKey::tryFromHex('invalid'));
    }

    public function testTryFromHexReturnsNullForWrongLength(): void
    {
        $this->assertNull(ConversationKey::tryFromHex(str_repeat('ab', 16)));
    }

    public function testTryFromBytesAcceptsCorrectLength(): void
    {
        $key = ConversationKey::tryFromBytes(random_bytes(32));

        $this->assertNotNull($key);
    }

    public function testTryFromBytesReturnsNullForWrongLength(): void
    {
        $this->assertNull(ConversationKey::tryFromBytes(random_bytes(16)));
    }

    public function testExposePassesBytesToClosure(): void
    {
        $bytes = random_bytes(32);
        $key = ConversationKey::tryFromBytes($bytes);
        $this->assertNotNull($key);

        $received = $key->expose(static fn (string $b): string => $b);

        $this->assertSame($bytes, $received);
    }

    public function testDeriveIsDeterministic(): void
    {
        $privateKey = PrivateKey::generate();
        $otherPublicKey = CryptoFixtures::signer()->derivePublicKey(PrivateKey::generate());

        $keyA = ConversationKey::derive($privateKey, $otherPublicKey, CryptoFixtures::ecdh());
        $keyB = ConversationKey::derive($privateKey, $otherPublicKey, CryptoFixtures::ecdh());

        $bytesA = $keyA->expose(static fn (string $b): string => $b);
        $bytesB = $keyB->expose(static fn (string $b): string => $b);

        $this->assertSame($bytesA, $bytesB);
    }

    public function testDeriveIsSymmetric(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyA = CryptoFixtures::signer()->derivePublicKey($privateKeyA);
        $publicKeyB = CryptoFixtures::signer()->derivePublicKey($privateKeyB);

        $keyAB = ConversationKey::derive($privateKeyA, $publicKeyB, CryptoFixtures::ecdh());
        $keyBA = ConversationKey::derive($privateKeyB, $publicKeyA, CryptoFixtures::ecdh());

        $bytesAB = $keyAB->expose(static fn (string $b): string => $b);
        $bytesBA = $keyBA->expose(static fn (string $b): string => $b);

        $this->assertSame($bytesAB, $bytesBA);
    }

    public function testDeriveProduces32ByteKey(): void
    {
        $privateKey = PrivateKey::generate();
        $otherPublicKey = CryptoFixtures::signer()->derivePublicKey(PrivateKey::generate());

        $key = ConversationKey::derive($privateKey, $otherPublicKey, CryptoFixtures::ecdh());

        $length = $key->expose(static fn (string $b): int => strlen($b));

        $this->assertSame(32, $length);
    }

    public function testZeroMakesExposeThrow(): void
    {
        $key = ConversationKey::tryFromBytes(random_bytes(32));
        $this->assertNotNull($key);

        $key->zero();

        $this->expectException(SecretKeyMaterialZeroedException::class);
        $key->expose(static fn (string $b): string => $b);
    }

    public function testZeroIsIdempotent(): void
    {
        $key = ConversationKey::tryFromBytes(random_bytes(32));
        $this->assertNotNull($key);

        $key->zero();
        $key->zero();

        $this->assertTrue($key->isZeroed());
    }

    public function testIsZeroedReflectsState(): void
    {
        $key = ConversationKey::tryFromBytes(random_bytes(32));
        $this->assertNotNull($key);

        $this->assertFalse($key->isZeroed());
        $key->zero();
        $this->assertTrue($key->isZeroed());
    }

    private static function ownKey(): PrivateKey
    {
        return PrivateKey::tryFromHex(str_repeat('0', 63).'1') ?? throw new RuntimeException('Invalid fixture private key');
    }

    private static function offCurveKey(): PublicKey
    {
        return PublicKey::tryFromHex(str_repeat('0', 63).'5') ?? throw new RuntimeException('Invalid fixture public key');
    }
}
