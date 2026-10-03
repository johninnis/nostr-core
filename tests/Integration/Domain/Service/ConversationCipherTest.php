<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\ConversationCipher;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;

final class ConversationCipherTest extends TestCase
{
    private ConversationCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new ConversationCipher(new Nip44Cipher(), CryptoFixtures::ecdh());
    }

    public function testThePeerOpensWhatWasEncryptedToIt(): void
    {
        $alice = KeyPair::generate(CryptoFixtures::signer());
        $bob = KeyPair::generate(CryptoFixtures::signer());

        $ciphertext = $this->cipher->encrypt('hello bob', $alice->getPrivateKey(), $bob->getPublicKey());

        $this->assertSame('hello bob', $this->cipher->decrypt($ciphertext, $bob->getPrivateKey(), $alice->getPublicKey()));
    }

    public function testAKeyOutsideTheConversationCannotOpenIt(): void
    {
        $alice = KeyPair::generate(CryptoFixtures::signer());
        $bob = KeyPair::generate(CryptoFixtures::signer());
        $eve = KeyPair::generate(CryptoFixtures::signer());

        $ciphertext = $this->cipher->encrypt('hello bob', $alice->getPrivateKey(), $bob->getPublicKey());

        $this->expectException(EncryptionException::class);

        $this->cipher->decrypt($ciphertext, $eve->getPrivateKey(), $alice->getPublicKey());
    }
}
