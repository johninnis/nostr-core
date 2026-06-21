<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;

final class KeyPairTest extends TestCase
{
    public function testCanGenerateKeyPair(): void
    {
        $keyPair = KeyPair::generate(CryptoFixtures::signer());

        $this->assertSame(64, strlen($keyPair->getPrivateKey()->toHex()));
        $this->assertSame(64, strlen($keyPair->getPublicKey()->toHex()));
    }

    public function testCanCreateFromPrivateKey(): void
    {
        $privateKey = PrivateKey::generate();
        $keyPair = KeyPair::fromPrivateKey($privateKey, CryptoFixtures::signer());

        $this->assertSame($privateKey->toHex(), $keyPair->getPrivateKey()->toHex());
        $this->assertTrue(
            $keyPair->getPublicKey()->equals(CryptoFixtures::signer()->derivePublicKey($privateKey))
        );
    }

    public function testGeneratedKeyPairsAreUnique(): void
    {
        $keyPair1 = KeyPair::generate(CryptoFixtures::signer());
        $keyPair2 = KeyPair::generate(CryptoFixtures::signer());

        $this->assertNotEquals($keyPair1->getPrivateKey()->toHex(), $keyPair2->getPrivateKey()->toHex());
        $this->assertNotEquals($keyPair1->getPublicKey()->toHex(), $keyPair2->getPublicKey()->toHex());
    }
}
