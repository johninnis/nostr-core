<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use PHPUnit\Framework\TestCase;

final class PrivateKeyTest extends TestCase
{
    private const VALID_PRIVATE_KEY_HEX = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';

    public function testCanCreateFromValidHex(): void
    {
        $privateKey = PrivateKey::fromHex(self::VALID_PRIVATE_KEY_HEX);

        $this->assertNotNull($privateKey);
        $this->assertSame(self::VALID_PRIVATE_KEY_HEX, $privateKey->toHex());
    }

    public function testReturnsNullForInvalidHexFormat(): void
    {
        $this->assertNull(PrivateKey::fromHex('invalid-hex'));
    }

    public function testReturnsNullForWrongLength(): void
    {
        $this->assertNull(PrivateKey::fromHex('123456'));
    }

    public function testCanGeneratePrivateKey(): void
    {
        $privateKey = PrivateKey::generate();

        $this->assertSame(64, \strlen($privateKey->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $privateKey->toHex());
    }

    public function testCanGetPublicKey(): void
    {
        $privateKey = PrivateKey::generate();
        $publicKey = $privateKey->getPublicKey();

        $this->assertSame(64, \strlen($publicKey->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $publicKey->toHex());
    }

    public function testCanSignMessage(): void
    {
        $privateKey = PrivateKey::generate();
        $signature = $privateKey->sign('test message');

        $this->assertSame(128, \strlen($signature->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $signature->toHex());
    }

    public function testCanConvertToBech32(): void
    {
        $privateKey = PrivateKey::fromHex(self::VALID_PRIVATE_KEY_HEX) ?? throw new \RuntimeException('Invalid test key');
        $bech32 = $privateKey->toBech32();

        $this->assertStringStartsWith('nsec1', $bech32);
    }

    public function testCanCreateFromBech32(): void
    {
        $privateKey = PrivateKey::fromHex(self::VALID_PRIVATE_KEY_HEX) ?? throw new \RuntimeException('Invalid test key');
        $bech32 = $privateKey->toBech32();
        $recreated = PrivateKey::fromBech32($bech32);

        $this->assertNotNull($recreated);
        $this->assertSame($privateKey->toHex(), $recreated->toHex());
    }

    public function testFromBech32ReturnsNullForInvalidPrefix(): void
    {
        $this->assertNull(PrivateKey::fromBech32('npub1abc'));
    }

    public function testGeneratedKeysAreUnique(): void
    {
        $key1 = PrivateKey::generate();
        $key2 = PrivateKey::generate();

        $this->assertNotEquals($key1->toHex(), $key2->toHex());
    }
}
