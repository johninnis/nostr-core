<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip04Cipher;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

#[IgnoreDeprecations]
final class Nip04CipherTest extends TestCase
{
    private const string SEPARATOR = '?iv=';

    public function testEncryptAndDecryptRoundTripsAsciiPlaintext(): void
    {
        $adapter = new Nip04Cipher();
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x42", 32));

        $payload = $adapter->encrypt('hello FROSTR', $key);
        $this->assertStringContainsString('?iv=', $payload);

        $key2 = SecretKeyMaterial::fromBytes(str_repeat("\x42", 32));
        $this->assertSame('hello FROSTR', $adapter->decrypt($payload, $key2));
    }

    public function testEncryptAndDecryptRoundTripsMultiByteUtf8(): void
    {
        $adapter = new Nip04Cipher();
        $message = 'naïve résumé 日本語 🔑';
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x99", 32));

        $payload = $adapter->encrypt($message, $key);
        $key2 = SecretKeyMaterial::fromBytes(str_repeat("\x99", 32));

        $this->assertSame($message, $adapter->decrypt($payload, $key2));
    }

    public function testEncryptRefusesPlaintextThatIsNotUtf8(): void
    {
        $this->expectExceptionObject(new EncryptionException('NIP-04 plaintext must be UTF-8'));

        new Nip04Cipher()->encrypt("caf\xC3", SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));
    }

    public function testDecryptRefusesPlaintextThatIsNotUtf8(): void
    {
        $adapter = new Nip04Cipher();
        $iv = str_repeat("\x07", 16);
        $ciphertext = openssl_encrypt("caf\xC3", 'aes-256-cbc', str_repeat("\x42", 32), OPENSSL_RAW_DATA, $iv);
        $this->assertNotFalse($ciphertext);
        $payload = base64_encode($ciphertext).self::SEPARATOR.base64_encode($iv);

        $this->expectExceptionObject(new EncryptionException('NIP-04 decryption failed'));

        $adapter->decrypt($payload, SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));
    }

    public function testDecryptKeepsAByteOrderMark(): void
    {
        $adapter = new Nip04Cipher();
        $message = "\u{FEFF}hello";
        $payload = $adapter->encrypt($message, SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));

        $this->assertSame($message, $adapter->decrypt($payload, SecretKeyMaterial::fromBytes(str_repeat("\x42", 32))));
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
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x77", 32));

        $first = $adapter->encrypt('deterministic', $key);
        $second = $adapter->encrypt('deterministic', SecretKeyMaterial::fromBytes(str_repeat("\x77", 32)));

        $this->assertSame($first, $second);
    }

    public function testDecryptRejectsPayloadMissingIvSeparator(): void
    {
        $adapter = new Nip04Cipher();
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(str_repeat('A', 60), $key);
    }

    public function testDecryptRejectsBadBase64Ciphertext(): void
    {
        $adapter = new Nip04Cipher();
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(str_repeat('!', 24).self::SEPARATOR.base64_encode(str_repeat("\x00", 16)), $key);
    }

    public function testDecryptRejectsBadBase64Iv(): void
    {
        $adapter = new Nip04Cipher();
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(base64_encode(str_repeat("\x00", 16)).self::SEPARATOR.str_repeat('!', 24), $key);
    }

    #[DataProvider('nonCanonicalBase64')]
    public function testDecryptRejectsAnIvThatIsNotCanonicalBase64(callable $alter): void
    {
        $adapter = new Nip04Cipher();
        [$ciphertext, $iv] = explode(self::SEPARATOR, $adapter->encrypt('a plaintext of two blocks', SecretKeyMaterial::fromBytes(str_repeat("\x42", 32))));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt($ciphertext.self::SEPARATOR.$alter($iv), SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));
    }

    #[DataProvider('nonCanonicalBase64')]
    public function testDecryptRejectsCiphertextThatIsNotCanonicalBase64(callable $alter): void
    {
        $adapter = new Nip04Cipher();
        [$ciphertext, $iv] = explode(self::SEPARATOR, $adapter->encrypt('a plaintext of two blocks', SecretKeyMaterial::fromBytes(str_repeat("\x42", 32))));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt($alter($ciphertext).self::SEPARATOR.$iv, SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));
    }

    /**
     * @return iterable<string, array{callable(string): string}>
     */
    public static function nonCanonicalBase64(): iterable
    {
        yield 'padding left off' => [static fn (string $encoded): string => rtrim($encoded, '=')];
        yield 'non-zero trailing bits' => [self::withNonZeroTrailingBits(...)];
        yield 'whitespace inside' => [static fn (string $encoded): string => substr($encoded, 0, 4).' '.substr($encoded, 4)];
    }

    public function testDecryptRejectsIvOfWrongLength(): void
    {
        $adapter = new Nip04Cipher();
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x00", 32));

        $this->expectException(EncryptionException::class);
        $adapter->decrypt(base64_encode(str_repeat("\x00", 32)).self::SEPARATOR.base64_encode(str_repeat("\x00", 8)), $key);
    }

    public function testEncryptRejectsKeyOfWrongLength(): void
    {
        $adapter = new Nip04Cipher();
        $key = SecretKeyMaterial::fromBytes(str_repeat("\x42", 32));

        $payload = $adapter->encrypt('hi', $key);

        $shortKey = SecretKeyMaterial::fromBytes(str_repeat("\x42", 32));
        $shortKey->zero();
        $this->expectException(SecretKeyMaterialZeroedException::class);
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
                $adapter->decrypt($payload, SecretKeyMaterial::fromBytes(str_repeat("\x00", 32)));
                $this->fail(sprintf('Expected "%s" to be rejected', $case));
            } catch (EncryptionException $exception) {
                $messages[$case] = $exception->getMessage();
            }
        }

        $this->assertSame(['NIP-04 decryption failed'], array_values(array_unique($messages)));
    }

    public function testTheLargestPlaintextItEncryptsIsOneItsOwnDecryptOpens(): void
    {
        $adapter = new Nip04Cipher();
        $plaintext = str_repeat('a', 65567);

        $payload = $adapter->encrypt($plaintext, SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));

        $this->assertSame($plaintext, $adapter->decrypt($payload, SecretKeyMaterial::fromBytes(str_repeat("\x42", 32))));
    }

    public function testEncryptRefusesAPlaintextWhosePayloadItsOwnDecryptWouldRefuse(): void
    {
        $this->expectExceptionObject(new EncryptionException('NIP-04 plaintext must be at most 65567 bytes'));

        new Nip04Cipher()->encrypt(str_repeat('a', 65568), SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));
    }

    public function testDecryptWithWrongKeyNeverRecoversPlaintext(): void
    {
        $adapter = new Nip04Cipher();
        $payload = $adapter->encrypt('secret', SecretKeyMaterial::fromBytes(str_repeat("\x42", 32)));

        $recovered = null;

        try {
            $recovered = $adapter->decrypt($payload, SecretKeyMaterial::fromBytes(str_repeat("\x99", 32)));
        } catch (EncryptionException) {
        }

        $this->assertNotSame('secret', $recovered);
    }

    private static function withNonZeroTrailingBits(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
        $padded = strlen($encoded) - strlen(rtrim($encoded, '='));
        $last = strlen($encoded) - $padded - 1;

        return substr($encoded, 0, $last).$alphabet[(int) strpos($alphabet, $encoded[$last]) + 1].str_repeat('=', $padded);
    }
}
