<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use PHPUnit\Framework\TestCase;

final class ConversationKeyTest extends TestCase
{
    public function testCanCreateFromValidHex(): void
    {
        $hex = str_repeat('ab', 32);
        $key = ConversationKey::fromHex($hex);

        $this->assertNotNull($key);
        $this->assertSame($hex, $key->toHex());
    }

    public function testReturnsNullForInvalidHex(): void
    {
        $this->assertNull(ConversationKey::fromHex('invalid'));
    }

    public function testReturnsNullForWrongLength(): void
    {
        $this->assertNull(ConversationKey::fromHex(str_repeat('ab', 16)));
    }

    public function testCanCreateFromBytes(): void
    {
        $bytes = random_bytes(32);
        $key = ConversationKey::fromBytes($bytes);

        $this->assertNotNull($key);
        $this->assertSame($bytes, $key->toBytes());
    }

    public function testReturnsNullForWrongByteLength(): void
    {
        $this->assertNull(ConversationKey::fromBytes(random_bytes(16)));
    }

    public function testEqualsComparesValues(): void
    {
        $hex = str_repeat('cd', 32);
        $key1 = ConversationKey::fromHex($hex);
        $key2 = ConversationKey::fromHex($hex);
        $key3 = ConversationKey::fromHex(str_repeat('ef', 32));

        $this->assertNotNull($key1);
        $this->assertNotNull($key2);
        $this->assertNotNull($key3);

        $this->assertTrue($key1->equals($key2));
        $this->assertFalse($key1->equals($key3));
    }

    public function testToStringReturnsHex(): void
    {
        $hex = str_repeat('ab', 32);
        $key = ConversationKey::fromHex($hex);
        $this->assertNotNull($key);

        $this->assertSame($hex, (string) $key);
    }

    public function testDeriveProducesConsistentKey(): void
    {
        $privateKey = PrivateKey::generate();
        $otherPrivateKey = PrivateKey::generate();

        $key1 = ConversationKey::derive($privateKey, $otherPrivateKey->getPublicKey());
        $key2 = ConversationKey::derive($privateKey, $otherPrivateKey->getPublicKey());

        $this->assertTrue($key1->equals($key2));
    }

    public function testDeriveIsSymmetric(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();

        $keyAB = ConversationKey::derive($privateKeyA, $privateKeyB->getPublicKey());
        $keyBA = ConversationKey::derive($privateKeyB, $privateKeyA->getPublicKey());

        $this->assertTrue($keyAB->equals($keyBA));
    }

    public function testDeriveProduces32ByteKey(): void
    {
        $privateKey = PrivateKey::generate();
        $otherPrivateKey = PrivateKey::generate();

        $key = ConversationKey::derive($privateKey, $otherPrivateKey->getPublicKey());

        $this->assertSame(64, strlen($key->toHex()));
        $this->assertSame(32, strlen($key->toBytes()));
    }
}
