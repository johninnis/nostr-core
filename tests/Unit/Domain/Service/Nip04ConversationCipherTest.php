<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\Nip04ConversationCipher;
use Innis\Nostr\Core\Domain\Service\Nip04EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

#[IgnoreDeprecations]
final class Nip04ConversationCipherTest extends TestCase
{
    public function testEncryptsUnderTheSharedSecretOfTheTwoKeys(): void
    {
        $sharedSecret = self::sharedSecret();
        $encryption = $this->createStub(Nip04EncryptionInterface::class);
        $encryption->method('encrypt')->willReturnCallback(static fn (string $plaintext, SecretKeyMaterial $secret): string => $secret === $sharedSecret ? 'sealed:'.$plaintext : 'wrong secret');

        $this->assertSame('sealed:gm', $this->cipher($encryption, $this->ecdhReturning($sharedSecret))->encrypt('gm', KeyMother::alice()->getPrivateKey(), KeyMother::bobPublicKey()));
    }

    public function testDecryptsUnderTheSharedSecretOfTheTwoKeys(): void
    {
        $sharedSecret = self::sharedSecret();
        $encryption = $this->createStub(Nip04EncryptionInterface::class);
        $encryption->method('decrypt')->willReturnCallback(static fn (string $payload, SecretKeyMaterial $secret): string => $secret === $sharedSecret ? 'opened' : 'wrong secret');

        $this->assertSame('opened', $this->cipher($encryption, $this->ecdhReturning($sharedSecret))->decrypt(self::payload(), KeyMother::bob()->getPrivateKey(), KeyMother::alicePublicKey()));
    }

    public function testZeroesTheSharedSecretAfterEncrypting(): void
    {
        $sharedSecret = self::sharedSecret();

        $this->cipher($this->createStub(Nip04EncryptionInterface::class), $this->ecdhReturning($sharedSecret))->encrypt('gm', KeyMother::alice()->getPrivateKey(), KeyMother::bobPublicKey());

        $this->assertTrue($sharedSecret->isZeroed());
    }

    public function testZeroesTheSharedSecretWhenDecryptingFails(): void
    {
        $sharedSecret = self::sharedSecret();
        $encryption = $this->createStub(Nip04EncryptionInterface::class);
        $encryption->method('decrypt')->willThrowException(new EncryptionException('NIP-04 decryption failed'));

        try {
            $this->cipher($encryption, $this->ecdhReturning($sharedSecret))->decrypt(self::payload(), KeyMother::bob()->getPrivateKey(), KeyMother::alicePublicKey());
        } catch (EncryptionException) {
        }

        $this->assertTrue($sharedSecret->isZeroed());
    }

    public function testAPeerKeyTheKeyAgreementRefusesFailsEncryption(): void
    {
        $this->expectExceptionObject(new EncryptionException('NIP-04 encryption failed'));

        $this->cipher($this->createStub(Nip04EncryptionInterface::class), $this->ecdhRefusing())->encrypt('gm', KeyMother::alice()->getPrivateKey(), KeyMother::bobPublicKey());
    }

    public function testAPeerKeyTheKeyAgreementRefusesFailsDecryptionWithTheOneNip04Failure(): void
    {
        $this->expectExceptionObject(new EncryptionException('NIP-04 decryption failed'));

        $this->cipher($this->createStub(Nip04EncryptionInterface::class), $this->ecdhRefusing())->decrypt(self::payload(), KeyMother::bob()->getPrivateKey(), KeyMother::alicePublicKey());
    }

    public function testADecryptionFailureForARefusedPeerKeyCarriesNoCause(): void
    {
        $cause = 'not thrown';

        try {
            $this->cipher($this->createStub(Nip04EncryptionInterface::class), $this->ecdhRefusing())->decrypt(self::payload(), KeyMother::bob()->getPrivateKey(), KeyMother::alicePublicKey());
        } catch (EncryptionException $failure) {
            $cause = $failure->getPrevious();
        }

        $this->assertNull($cause);
    }

    public function testAPlaintextThatIsNotUtf8IsRefusedBeforeTheKeyAgreement(): void
    {
        $this->expectExceptionObject(new EncryptionException('NIP-04 plaintext must be UTF-8'));

        $this->cipher($this->createStub(Nip04EncryptionInterface::class), $this->ecdhRefusing())->encrypt("caf\xC3", KeyMother::alice()->getPrivateKey(), KeyMother::bobPublicKey());
    }

    public function testAnOverLongPlaintextIsRefusedBeforeTheKeyAgreement(): void
    {
        $this->expectExceptionObject(new EncryptionException('NIP-04 plaintext must be at most 65567 bytes'));

        $this->cipher($this->createStub(Nip04EncryptionInterface::class), $this->ecdhRefusing())->encrypt(str_repeat('a', 65568), KeyMother::alice()->getPrivateKey(), KeyMother::bobPublicKey());
    }

    public function testAPayloadShorterThanTheFloorIsRefusedWithoutAKeyAgreement(): void
    {
        $ecdh = $this->createMock(EcdhServiceInterface::class);
        $ecdh->expects($this->never())->method('computeSharedX');

        try {
            $this->cipher($this->createStub(Nip04EncryptionInterface::class), $ecdh)->decrypt(str_repeat('a', 51), KeyMother::bob()->getPrivateKey(), KeyMother::alicePublicKey());
        } catch (EncryptionException) {
        }
    }

    public function testAPayloadLongerThanTheCeilingIsRefusedWithoutAKeyAgreement(): void
    {
        $ecdh = $this->createMock(EcdhServiceInterface::class);
        $ecdh->expects($this->never())->method('computeSharedX');

        try {
            $this->cipher($this->createStub(Nip04EncryptionInterface::class), $ecdh)->decrypt(str_repeat('a', 87473), KeyMother::bob()->getPrivateKey(), KeyMother::alicePublicKey());
        } catch (EncryptionException) {
        }
    }

    private function cipher(Nip04EncryptionInterface $encryption, EcdhServiceInterface $ecdh): Nip04ConversationCipher
    {
        return new Nip04ConversationCipher($encryption, $ecdh);
    }

    private function ecdhReturning(SecretKeyMaterial $sharedSecret): EcdhServiceInterface
    {
        $ecdh = $this->createStub(EcdhServiceInterface::class);
        $ecdh->method('computeSharedX')->willReturn($sharedSecret);

        return $ecdh;
    }

    private function ecdhRefusing(): EcdhServiceInterface
    {
        $ecdh = $this->createStub(EcdhServiceInterface::class);
        $ecdh->method('computeSharedX')->willThrowException(new EcdhException('ECDH public key is not a valid curve point'));

        return $ecdh;
    }

    private static function payload(): string
    {
        return str_repeat('A', 24).'?iv='.str_repeat('A', 24);
    }

    private static function sharedSecret(): SecretKeyMaterial
    {
        return SecretKeyMaterial::fromBytes(str_repeat("\x01", 32));
    }
}
