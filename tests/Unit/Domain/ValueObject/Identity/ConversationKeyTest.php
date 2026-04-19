<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use PHPUnit\Framework\TestCase;

final class ConversationKeyTest extends TestCase
{
    use WithCryptoServices;

    public function testFromHexAcceptsValidHex(): void
    {
        $key = ConversationKey::fromHex(str_repeat('ab', 32));

        $this->assertNotNull($key);
    }

    public function testFromHexReturnsNullForInvalidCharacters(): void
    {
        $this->assertNull(ConversationKey::fromHex('invalid'));
    }

    public function testFromHexReturnsNullForWrongLength(): void
    {
        $this->assertNull(ConversationKey::fromHex(str_repeat('ab', 16)));
    }

    public function testFromBytesAcceptsCorrectLength(): void
    {
        $key = ConversationKey::fromBytes(random_bytes(32));

        $this->assertNotNull($key);
    }

    public function testFromBytesReturnsNullForWrongLength(): void
    {
        $this->assertNull(ConversationKey::fromBytes(random_bytes(16)));
    }

    public function testExposePassesBytesToClosure(): void
    {
        $bytes = random_bytes(32);
        $key = ConversationKey::fromBytes($bytes);
        $this->assertNotNull($key);

        $received = $key->expose(static fn (string $b): string => $b);

        $this->assertSame($bytes, $received);
    }

    public function testDeriveIsDeterministic(): void
    {
        $privateKey = PrivateKey::generate();
        $otherPublicKey = $this->signatureService()->derivePublicKey(PrivateKey::generate());

        $keyA = ConversationKey::derive($privateKey, $otherPublicKey, $this->ecdhService());
        $keyB = ConversationKey::derive($privateKey, $otherPublicKey, $this->ecdhService());

        $bytesA = $keyA->expose(static fn (string $b): string => $b);
        $bytesB = $keyB->expose(static fn (string $b): string => $b);

        $this->assertSame($bytesA, $bytesB);
    }

    public function testDeriveIsSymmetric(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyA = $this->signatureService()->derivePublicKey($privateKeyA);
        $publicKeyB = $this->signatureService()->derivePublicKey($privateKeyB);

        $keyAB = ConversationKey::derive($privateKeyA, $publicKeyB, $this->ecdhService());
        $keyBA = ConversationKey::derive($privateKeyB, $publicKeyA, $this->ecdhService());

        $bytesAB = $keyAB->expose(static fn (string $b): string => $b);
        $bytesBA = $keyBA->expose(static fn (string $b): string => $b);

        $this->assertSame($bytesAB, $bytesBA);
    }

    public function testDeriveProduces32ByteKey(): void
    {
        $privateKey = PrivateKey::generate();
        $otherPublicKey = $this->signatureService()->derivePublicKey(PrivateKey::generate());

        $key = ConversationKey::derive($privateKey, $otherPublicKey, $this->ecdhService());

        $length = $key->expose(static fn (string $b): int => strlen($b));

        $this->assertSame(32, $length);
    }

    public function testZeroMakesExposeThrow(): void
    {
        $key = ConversationKey::fromBytes(random_bytes(32));
        $this->assertNotNull($key);

        $key->zero();

        $this->expectException(SecretKeyMaterialZeroedException::class);
        $key->expose(static fn (string $b): string => $b);
    }

    public function testZeroIsIdempotent(): void
    {
        $key = ConversationKey::fromBytes(random_bytes(32));
        $this->assertNotNull($key);

        $key->zero();
        $key->zero();

        $this->assertTrue($key->isZeroed());
    }

    public function testIsZeroedReflectsState(): void
    {
        $key = ConversationKey::fromBytes(random_bytes(32));
        $this->assertNotNull($key);

        $this->assertFalse($key->isZeroed());
        $key->zero();
        $this->assertTrue($key->isZeroed());
    }
}
