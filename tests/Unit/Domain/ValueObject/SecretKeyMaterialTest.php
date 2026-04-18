<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SecretKeyMaterialTest extends TestCase
{
    private const EXACT_LENGTH_BYTES = "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20";

    public function testConstructorRejectsShortInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SecretKeyMaterial(str_repeat("\x00", 31));
    }

    public function testConstructorRejectsLongInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SecretKeyMaterial(str_repeat("\x00", 33));
    }

    public function testConstructorAcceptsExactLength(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);

        $this->assertFalse($material->isZeroed());
    }

    public function testRandomProducesDistinctInstances(): void
    {
        $first = SecretKeyMaterial::random()->expose(static fn (string $bytes): string => $bytes);
        $second = SecretKeyMaterial::random()->expose(static fn (string $bytes): string => $bytes);

        $this->assertNotSame($first, $second);
    }

    public function testExposePassesBytesToClosure(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);

        $received = $material->expose(static fn (string $bytes): string => $bytes);

        $this->assertSame(self::EXACT_LENGTH_BYTES, $received);
    }

    public function testExposeReturnsClosureReturnValue(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);

        $this->assertSame(42, $material->expose(static fn (string $bytes): int => 42));
    }

    public function testExposeForcesCopyNotAlias(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);

        $material->expose(static function (string $bytes): void {
            sodium_memzero($bytes);
        });

        $readBack = $material->expose(static fn (string $bytes): string => $bytes);

        $this->assertSame(self::EXACT_LENGTH_BYTES, $readBack);
    }

    public function testZeroMakesExposeThrow(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);
        $material->zero();

        $this->expectException(SecretKeyMaterialZeroedException::class);
        $material->expose(static fn (string $bytes): string => $bytes);
    }

    public function testZeroIsIdempotent(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);

        $material->zero();
        $material->zero();

        $this->assertTrue($material->isZeroed());
    }

    public function testIsZeroedReflectsState(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);
        $this->assertFalse($material->isZeroed());

        $material->zero();
        $this->assertTrue($material->isZeroed());
    }

    public function testDestructorZeros(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);

        $material->__destruct();

        $this->assertTrue($material->isZeroed());
    }

    public function testExceptionMessageDoesNotLeakBytes(): void
    {
        $material = new SecretKeyMaterial(self::EXACT_LENGTH_BYTES);
        $material->zero();

        try {
            $material->expose(static fn (string $bytes): string => $bytes);
            $this->fail('Expected SecretKeyMaterialZeroedException');
        } catch (SecretKeyMaterialZeroedException $e) {
            $message = $e->getMessage();
            $this->assertStringNotContainsString(bin2hex(self::EXACT_LENGTH_BYTES), $message);
            $this->assertStringNotContainsString(base64_encode(self::EXACT_LENGTH_BYTES), $message);
            $this->assertStringNotContainsString(self::EXACT_LENGTH_BYTES, $message);
        }
    }
}
