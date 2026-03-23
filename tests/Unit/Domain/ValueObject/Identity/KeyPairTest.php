<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use PHPUnit\Framework\TestCase;

final class KeyPairTest extends TestCase
{
    public function testCanGenerateKeyPair(): void
    {
        $keyPair = KeyPair::generate();

        $this->assertInstanceOf(KeyPair::class, $keyPair);
        $this->assertSame(64, \strlen($keyPair->getPrivateKey()->toHex()));
        $this->assertSame(64, \strlen($keyPair->getPublicKey()->toHex()));
    }

    public function testCanCreateFromPrivateKey(): void
    {
        $privateKey = PrivateKey::generate();
        $keyPair = KeyPair::fromPrivateKey($privateKey);

        $this->assertTrue($keyPair->getPrivateKey()->toHex() === $privateKey->toHex());
        $this->assertTrue($keyPair->getPublicKey()->equals($privateKey->getPublicKey()));
    }

    public function testThrowsExceptionWhenKeysDoNotMatch(): void
    {
        $privateKey = PrivateKey::generate();
        $wrongPublicKey = PrivateKey::generate()->getPublicKey();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key does not match public key');

        new KeyPair($privateKey, $wrongPublicKey);
    }

    public function testCanSignWithKeyPair(): void
    {
        $keyPair = KeyPair::generate();
        $message = 'test message';

        $signature = $keyPair->sign($message);

        $this->assertSame(128, \strlen($signature->toHex()));
    }

    public function testCanVerifyWithKeyPair(): void
    {
        $keyPair = KeyPair::generate();
        $message = 'test message';

        $signature = $keyPair->sign($message);
        $isValid = $keyPair->verify($message, $signature);

        $this->assertTrue($isValid);
    }

    public function testGeneratedKeyPairsAreUnique(): void
    {
        $keyPair1 = KeyPair::generate();
        $keyPair2 = KeyPair::generate();

        $this->assertNotEquals($keyPair1->getPrivateKey()->toHex(), $keyPair2->getPrivateKey()->toHex());
        $this->assertNotEquals($keyPair1->getPublicKey()->toHex(), $keyPair2->getPublicKey()->toHex());
    }
}
