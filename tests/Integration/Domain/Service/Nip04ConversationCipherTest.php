<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\Nip04ConversationCipher;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip04Cipher;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[IgnoreDeprecations]
final class Nip04ConversationCipherTest extends TestCase
{
    private const string OFF_CURVE_X_HEX = '0000000000000000000000000000000000000000000000000000000000000005';

    private Nip04ConversationCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new Nip04ConversationCipher(new Nip04Cipher(), CryptoFixtures::ecdh());
    }

    public function testThePeerOpensWhatWasEncryptedToIt(): void
    {
        $alice = KeyPair::generate(CryptoFixtures::signer());
        $bob = KeyPair::generate(CryptoFixtures::signer());

        $payload = $this->cipher->encrypt('hello bob', $alice->getPrivateKey(), $bob->getPublicKey());

        $this->assertSame('hello bob', $this->cipher->decrypt($payload, $bob->getPrivateKey(), $alice->getPublicKey()));
    }

    public function testASenderKeyOffTheCurveFailsDecryptionWithTheOneNip04Failure(): void
    {
        $alice = KeyPair::generate(CryptoFixtures::signer());
        $bob = KeyPair::generate(CryptoFixtures::signer());
        $payload = $this->cipher->encrypt('hello bob', $alice->getPrivateKey(), $bob->getPublicKey());

        $this->expectExceptionObject(new EncryptionException('NIP-04 decryption failed'));

        $this->cipher->decrypt($payload, $bob->getPrivateKey(), self::offCurveKey());
    }

    public function testARecipientKeyOffTheCurveFailsEncryption(): void
    {
        $alice = KeyPair::generate(CryptoFixtures::signer());

        $this->expectExceptionObject(new EncryptionException('NIP-04 encryption failed'));

        $this->cipher->encrypt('hello', $alice->getPrivateKey(), self::offCurveKey());
    }

    private static function offCurveKey(): PublicKey
    {
        return PublicKey::tryFromHex(self::OFF_CURVE_X_HEX) ?? throw new RuntimeException('Invalid fixture public key');
    }
}
