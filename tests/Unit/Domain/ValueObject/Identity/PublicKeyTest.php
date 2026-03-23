<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use PHPUnit\Framework\TestCase;

final class PublicKeyTest extends TestCase
{
    private const VALID_PUBLIC_KEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    public function testCanCreateFromValidHex(): void
    {
        $publicKey = PublicKey::fromHex(self::VALID_PUBLIC_KEY_HEX);

        $this->assertNotNull($publicKey);
        $this->assertSame(self::VALID_PUBLIC_KEY_HEX, $publicKey->toHex());
        $this->assertSame(self::VALID_PUBLIC_KEY_HEX, (string) $publicKey);
    }

    public function testReturnsNullForInvalidHexFormat(): void
    {
        $this->assertNull(PublicKey::fromHex('invalid-hex'));
    }

    public function testReturnsNullForWrongLength(): void
    {
        $this->assertNull(PublicKey::fromHex('123456'));
    }

    public function testCanConvertToBech32(): void
    {
        $publicKey = PublicKey::fromHex(self::VALID_PUBLIC_KEY_HEX) ?? throw new \RuntimeException('Invalid test pubkey');
        $bech32 = $publicKey->toBech32();

        $this->assertStringStartsWith('npub1', $bech32);
        $this->assertSame(63, \strlen($bech32));
    }

    public function testCanCreateFromBech32(): void
    {
        $publicKey = PublicKey::fromHex(self::VALID_PUBLIC_KEY_HEX) ?? throw new \RuntimeException('Invalid test pubkey');
        $bech32 = $publicKey->toBech32();
        $recreatedKey = PublicKey::fromBech32($bech32);

        $this->assertNotNull($recreatedKey);
        $this->assertTrue($publicKey->equals($recreatedKey));
    }

    public function testEqualsWorksCorrectly(): void
    {
        $publicKey1 = PublicKey::fromHex(self::VALID_PUBLIC_KEY_HEX) ?? throw new \RuntimeException('Invalid test pubkey');
        $publicKey2 = PublicKey::fromHex(self::VALID_PUBLIC_KEY_HEX);
        $this->assertNotNull($publicKey2);
        $publicKey3 = PublicKey::fromHex(str_repeat('a', 64));
        $this->assertNotNull($publicKey3);

        $this->assertTrue($publicKey1->equals($publicKey2));
        $this->assertFalse($publicKey1->equals($publicKey3));
    }

    public function testVerifySignatureMethodExists(): void
    {
        $publicKey = PublicKey::fromHex(self::VALID_PUBLIC_KEY_HEX) ?? throw new \RuntimeException('Invalid test pubkey');
        $signature = Signature::fromHex(str_repeat('a', 128)) ?? throw new \RuntimeException('Invalid test sig');

        $result = $publicKey->verify('test message', $signature);
        $this->assertIsBool($result);
    }
}
