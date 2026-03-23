<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase
{
    private const VALID_SIGNATURE_HEX = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';

    public function testCanCreateFromValidHex(): void
    {
        $signature = Signature::fromHex(self::VALID_SIGNATURE_HEX);

        $this->assertNotNull($signature);
        $this->assertSame(self::VALID_SIGNATURE_HEX, $signature->toHex());
        $this->assertSame(self::VALID_SIGNATURE_HEX, (string) $signature);
    }

    public function testReturnsNullForInvalidHexFormat(): void
    {
        $this->assertNull(Signature::fromHex('invalid-hex'));
    }

    public function testReturnsNullForWrongLength(): void
    {
        $this->assertNull(Signature::fromHex('123456'));
    }

    public function testReturnsNullForTooShort(): void
    {
        $this->assertNull(Signature::fromHex(str_repeat('a', 64)));
    }

    public function testEqualsWorksCorrectly(): void
    {
        $signature1 = Signature::fromHex(self::VALID_SIGNATURE_HEX) ?? throw new \RuntimeException('Invalid test sig');
        $signature2 = Signature::fromHex(self::VALID_SIGNATURE_HEX);
        $this->assertNotNull($signature2);
        $signature3 = Signature::fromHex(str_repeat('f', 128));
        $this->assertNotNull($signature3);

        $this->assertTrue($signature1->equals($signature2));
        $this->assertFalse($signature1->equals($signature3));
    }
}
