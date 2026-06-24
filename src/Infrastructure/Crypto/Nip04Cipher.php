<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Deprecated;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\Nip04EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Override;

final class Nip04Cipher implements Nip04EncryptionInterface
{
    private const string CIPHER = 'aes-256-cbc';
    private const int IV_LENGTH = 16;
    private const int KEY_LENGTH = 32;

    public function __construct(
        private readonly RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator(),
    ) {
    }

    #[Override]
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use Nip44Cipher instead')]
    public function encrypt(string $plaintext, SecretKeyMaterial $sharedSecret): string
    {
        $iv = $this->randomBytes->bytes(self::IV_LENGTH);

        $ciphertext = $sharedSecret->expose(static function (string $key) use ($plaintext, $iv): string {
            if (self::KEY_LENGTH !== strlen($key)) {
                throw new EncryptionException(sprintf('NIP-04 shared secret must be %d bytes', self::KEY_LENGTH));
            }
            $ct = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            if (false === $ct) {
                throw new EncryptionException('NIP-04 encryption failed');
            }

            return $ct;
        });

        return base64_encode($ciphertext).'?iv='.base64_encode($iv);
    }

    #[Override]
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use Nip44Cipher instead')]
    public function decrypt(string $payload, SecretKeyMaterial $sharedSecret): string
    {
        $separatorPos = strpos($payload, '?iv=');
        if (false === $separatorPos) {
            throw new EncryptionException('NIP-04 payload missing ?iv= separator');
        }

        $ciphertextB64 = substr($payload, 0, $separatorPos);
        $ivB64 = substr($payload, $separatorPos + 4);

        $ciphertext = base64_decode($ciphertextB64, true);
        $iv = base64_decode($ivB64, true);
        if (false === $ciphertext) {
            throw new EncryptionException('NIP-04 ciphertext is not valid base64');
        }
        if (false === $iv) {
            throw new EncryptionException('NIP-04 IV is not valid base64');
        }
        if (self::IV_LENGTH !== strlen($iv)) {
            throw new EncryptionException(sprintf('NIP-04 IV must be %d bytes', self::IV_LENGTH));
        }

        $plaintext = $sharedSecret->expose(static function (string $key) use ($ciphertext, $iv): string {
            if (self::KEY_LENGTH !== strlen($key)) {
                throw new EncryptionException(sprintf('NIP-04 shared secret must be %d bytes', self::KEY_LENGTH));
            }
            $pt = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            if (false === $pt) {
                throw new EncryptionException('NIP-04 decryption failed');
            }

            return $pt;
        });

        return $plaintext;
    }
}
