<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip04Cipher;
use Override;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

#[IgnoreDeprecations]
final class Nip04CipherTest extends TestCase
{
    private const string SEPARATOR = '?iv=';

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
            #[Override]
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
        $adapter->decrypt(str_repeat('A', 60), $key);
    }

    public function testDecryptRejectsBadBase64Ciphertext(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(str_repeat('!', 24).self::SEPARATOR.base64_encode(str_repeat("\x00", 16)), $key);
    }

    public function testDecryptRejectsBadBase64Iv(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(base64_encode(str_repeat("\x00", 16)).self::SEPARATOR.str_repeat('!', 24), $key);
    }

    public function testDecryptRejectsIvOfWrongLength(): void
    {
        $adapter = new Nip04Cipher();
        $key = new SecretKeyMaterial(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(base64_encode(str_repeat("\x00", 32)).self::SEPARATOR.base64_encode(str_repeat("\x00", 8)), $key);
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

    public function testEveryRejectionReportsTheSameMessage(): void
    {
        $adapter = new Nip04Cipher();
        $validIv = base64_encode(str_repeat("\x00", 16));
        $validBlock = base64_encode(str_repeat("\x00", 16));

        $rejections = [
            'no separator' => str_repeat('A', 60),
            'bad base64 ciphertext' => str_repeat('!', 24).self::SEPARATOR.$validIv,
            'bad base64 iv' => $validBlock.self::SEPARATOR.str_repeat('!', 24),
            'wrong iv length' => base64_encode(str_repeat("\x00", 32)).self::SEPARATOR.base64_encode(str_repeat("\x00", 8)),
            'undecryptable ciphertext' => $validBlock.self::SEPARATOR.$validIv,
            'too short' => 'AAAA'.self::SEPARATOR.$validIv,
            'too long' => str_repeat('A', 90000).self::SEPARATOR.$validIv,
        ];

        $messages = [];

        foreach ($rejections as $case => $payload) {
            try {
                $adapter->decrypt($payload, new SecretKeyMaterial(str_repeat("\x00", 32)));
                $this->fail(sprintf('Expected "%s" to be rejected', $case));
            } catch (EncryptionException $exception) {
                $messages[$case] = $exception->getMessage();
            }
        }

        $this->assertSame(['NIP-04 decryption failed'], array_values(array_unique($messages)));
    }

    public function testDecryptWithWrongKeyNeverRecoversPlaintext(): void
    {
        $adapter = new Nip04Cipher();
        $payload = $adapter->encrypt('secret', new SecretKeyMaterial(str_repeat("\x42", 32)));

        $recovered = null;

        try {
            $recovered = $adapter->decrypt($payload, new SecretKeyMaterial(str_repeat("\x99", 32)));
        } catch (EncryptionException) {
        }

        $this->assertNotSame('secret', $recovered);
    }
}
