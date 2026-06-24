<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip04Cipher;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

#[IgnoreDeprecations]
final class Nip04CipherTest extends TestCase
{
    public function testEncryptAndDecryptRoundTripsAsciiPlaintext(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x42", 32));

        $payload = $adapter->encrypt('hello FROSTR', $key);
        $this->assertStringContainsString('?iv=', $payload);

        $key2 = new SecretKeyMaterial(str_repeat("\x42", 32));
        $this->assertSame('hello FROSTR', $adapter->decrypt($payload, $key2));
    }

    public function testEncryptAndDecryptRoundTripsMultiByteUtf8(): void
    {
        $adapter = new Nip04Cipher();
        $message = 'naïve résumé 日本語 🔑';
        $key = new SecretKeyMaterial(str_repeat("\x99", 32));

        $payload = $adapter->encrypt($message, $key);
        $key2 = new SecretKeyMaterial(str_repeat("\x99", 32));

        $this->assertSame($message, $adapter->decrypt($payload, $key2));
    }

    public function testEncryptIsDeterministicWithPinnedIv(): void
    {
        $adapter = new Nip04Cipher(new class implements RandomBytesGeneratorInterface {
            public function bytes(int $length): string
            {
                return str_repeat("\x10", $length);
            }
        });
        $key = new SecretKeyMaterial(str_repeat("\x77", 32));

        $first = $adapter->encrypt('deterministic', $key);
        $second = $adapter->encrypt('deterministic', new SecretKeyMaterial(str_repeat("\x77", 32)));

        $this->assertSame($first, $second);
    }

    public function testDecryptRejectsPayloadMissingIvSeparator(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt('YWJjZA==', $key);
    }

    public function testDecryptRejectsBadBase64Ciphertext(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt('!!!?iv=AAAAAAAAAAAAAAAAAAAAAA==', $key);
    }

    public function testDecryptRejectsBadBase64Iv(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt('YWJjZA==?iv=!!!', $key);
    }

    public function testDecryptRejectsIvOfWrongLength(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt('YWJjZA==?iv='.base64_encode(str_repeat("\x00", 8)), $key);
    }

    public function testEncryptRejectsKeyOfWrongLength(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x42", 32));

        $payload = $adapter->encrypt('hi', $key);

        $shortKey = new SecretKeyMaterial(str_repeat("\x42", 32));
        $shortKey->zero();
        $this->expectException(\Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException::class);
        $adapter->decrypt($payload, $shortKey);
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $adapter = new Nip04Cipher();
        $payload = $adapter->encrypt('secret', new SecretKeyMaterial(str_repeat("\x42", 32)));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt($payload, new SecretKeyMaterial(str_repeat("\x99", 32)));
    }
}
