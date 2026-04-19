<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Adapter;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Infrastructure\Adapter\NativeRandomBytesGeneratorAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1SignatureAdapter;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Secp256k1SignatureAdapterTest extends TestCase
{
    public function testCreateYieldsFfiServiceWhenLibraryAvailable(): void
    {
        $service = Secp256k1SignatureAdapter::create();

        $privateKey = PrivateKey::generate();
        $message = random_bytes(32);

        $signature = $service->sign($privateKey, $message);
        $this->assertTrue($service->verify($service->derivePublicKey($privateKey), $message, $signature));
    }

    public function testSignAndVerifyRoundTripViaPurePhp(): void
    {
        $service = $this->purePhpService();
        $privateKey = PrivateKey::generate();
        $message = random_bytes(32);

        $signature = $service->sign($privateKey, $message);
        $publicKey = $service->derivePublicKey($privateKey);

        $this->assertTrue($service->verify($publicKey, $message, $signature));
    }

    public function testSignAndVerifyRoundTripViaFfi(): void
    {
        $service = $this->ffiService();
        $privateKey = PrivateKey::generate();
        $message = random_bytes(32);

        $signature = $service->sign($privateKey, $message);
        $publicKey = $service->derivePublicKey($privateKey);

        $this->assertTrue($service->verify($publicKey, $message, $signature));
    }

    public function testDerivePublicKeyAgreesAcrossPaths(): void
    {
        $privateKey = PrivateKey::generate();
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();

        $ffiPublicKey = $ffiService->derivePublicKey($privateKey);
        $purePhpPublicKey = $purePhpService->derivePublicKey($privateKey);

        $this->assertTrue($ffiPublicKey->equals($purePhpPublicKey));
    }

    public function testCrossPathVerification(): void
    {
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();

        $privateKey = PrivateKey::generate();
        $publicKey = $ffiService->derivePublicKey($privateKey);
        $message = random_bytes(32);

        $ffiSignature = $ffiService->sign($privateKey, $message);
        $purePhpSignature = $purePhpService->sign($privateKey, $message);

        $this->assertTrue($purePhpService->verify($publicKey, $message, $ffiSignature));
        $this->assertTrue($ffiService->verify($publicKey, $message, $purePhpSignature));
    }

    public function testVerifyRejectsTamperedMessageViaFfi(): void
    {
        $this->assertVerifyRejectsTamperedMessage($this->ffiService());
    }

    public function testVerifyRejectsTamperedMessageViaPurePhp(): void
    {
        $this->assertVerifyRejectsTamperedMessage($this->purePhpService());
    }

    public function testVerifyRejectsMalformedSignatureHexLength(): void
    {
        $service = $this->purePhpService();
        $privateKey = PrivateKey::generate();
        $publicKey = $service->derivePublicKey($privateKey);

        $tooShort = Signature::fromHex(str_repeat('a', 126))
            ?? throw new RuntimeException('test setup: short hex not accepted by Signature::fromHex');

        $this->assertFalse($service->verify($publicKey, random_bytes(32), $tooShort));
    }

    public function testSignPurePhpAcceptsNonThirtyTwoByteMessage(): void
    {
        $service = $this->purePhpService();
        $privateKey = PrivateKey::generate();

        $signature = $service->sign($privateKey, 'short message');
        $publicKey = $service->derivePublicKey($privateKey);

        $this->assertTrue($service->verify($publicKey, 'short message', $signature));
    }

    private function assertVerifyRejectsTamperedMessage(Secp256k1SignatureAdapter $service): void
    {
        $privateKey = PrivateKey::generate();
        $publicKey = $service->derivePublicKey($privateKey);
        $message = random_bytes(32);

        $signature = $service->sign($privateKey, $message);
        $tamperedMessage = $message.chr(0);

        $this->assertFalse($service->verify($publicKey, $tamperedMessage, $signature));
    }

    private function ffiService(): Secp256k1SignatureAdapter
    {
        $randomBytes = new NativeRandomBytesGeneratorAdapter();
        $ffi = LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32))
            ?? self::markTestSkipped('libsecp256k1 FFI unavailable');

        return new Secp256k1SignatureAdapter($ffi, $randomBytes);
    }

    private function purePhpService(): Secp256k1SignatureAdapter
    {
        return new Secp256k1SignatureAdapter(null, new NativeRandomBytesGeneratorAdapter());
    }
}
