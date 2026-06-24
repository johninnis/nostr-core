<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use Innis\Nostr\Core\Infrastructure\Crypto\NativeRandomBytesGenerator;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Secp256k1SignerTest extends TestCase
{
    public function testCreateYieldsFfiServiceWhenLibraryAvailable(): void
    {
        $service = Secp256k1Signer::create();

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

    public function testVerifyRejectsWellFormedButInvalidSignature(): void
    {
        $service = $this->purePhpService();
        $privateKey = PrivateKey::generate();
        $publicKey = $service->derivePublicKey($privateKey);

        $invalidSignature = Signature::fromHex(str_repeat('a', 128))
            ?? throw new RuntimeException('test setup: valid-length hex not accepted by Signature::fromHex');

        $this->assertFalse($service->verify($publicKey, random_bytes(32), $invalidSignature));
    }

    public function testSignRejectsNonThirtyTwoByteMessage(): void
    {
        $service = $this->purePhpService();
        $privateKey = PrivateKey::generate();

        $this->expectException(InvalidArgumentException::class);
        $service->sign($privateKey, 'short message');
    }

    public function testVerifyRejectsOffCurvePublicKeyViaPurePhp(): void
    {
        $offCurvePublicKey = PublicKey::fromHex('eefdea4cdb677750a420fee807eacf21eb9898ae79b9768766e4faa04a2d4a34')
            ?? throw new RuntimeException('test setup: off-curve x not accepted by PublicKey::fromHex');

        $signature = Signature::fromHex(str_repeat('a', 128))
            ?? throw new RuntimeException('test setup: valid-length hex not accepted by Signature::fromHex');

        $this->assertFalse($this->purePhpService()->verify($offCurvePublicKey, str_repeat("\x00", 32), $signature));
    }

    public function testVerifyRejectsOffCurvePublicKeyViaFfi(): void
    {
        $offCurvePublicKey = PublicKey::fromHex('eefdea4cdb677750a420fee807eacf21eb9898ae79b9768766e4faa04a2d4a34')
            ?? throw new RuntimeException('test setup: off-curve x not accepted by PublicKey::fromHex');

        $signature = Signature::fromHex(str_repeat('a', 128))
            ?? throw new RuntimeException('test setup: valid-length hex not accepted by Signature::fromHex');

        $this->assertFalse($this->ffiService()->verify($offCurvePublicKey, str_repeat("\x00", 32), $signature));
    }

    private function assertVerifyRejectsTamperedMessage(Secp256k1Signer $service): void
    {
        $privateKey = PrivateKey::generate();
        $publicKey = $service->derivePublicKey($privateKey);
        $message = random_bytes(32);

        $signature = $service->sign($privateKey, $message);
        $tamperedMessage = $message.chr(0);

        $this->assertFalse($service->verify($publicKey, $tamperedMessage, $signature));
    }

    private function ffiService(): Secp256k1Signer
    {
        $randomBytes = new NativeRandomBytesGenerator();
        $ffi = LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32))
            ?? self::markTestSkipped('libsecp256k1 FFI unavailable');

        return new Secp256k1Signer($ffi, $randomBytes);
    }

    private function purePhpService(): Secp256k1Signer
    {
        return new Secp256k1Signer(null, new NativeRandomBytesGenerator());
    }
}
