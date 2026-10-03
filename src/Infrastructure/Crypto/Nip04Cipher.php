<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Deprecated;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\Base64Codec;
use Innis\Nostr\Core\Domain\Service\Nip04EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Override;

final readonly class Nip04Cipher implements Nip04EncryptionInterface
{
    private const string CIPHER = 'aes-256-cbc';
    private const int IV_LENGTH = 16;
    private const string IV_SEPARATOR = '?iv=';
    private const int MIN_PAYLOAD_LENGTH = 52;
    private const int MAX_PAYLOAD_LENGTH = 87472;
    private const int MAX_PLAINTEXT_LENGTH = 65567;
    private const string DECRYPTION_FAILED = 'NIP-04 decryption failed';

    public function __construct(
        private RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator(),
    ) {
    }

    #[Override]
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use Nip44Cipher instead')]
    public function encrypt(string $plaintext, SecretKeyMaterial $sharedSecret): string
    {
        // Deliberate: the largest plaintext whose payload fits the decrypt ceiling, so this cipher never writes a payload it would refuse to open — see ADR-0118
        if (strlen($plaintext) > self::MAX_PLAINTEXT_LENGTH) {
            throw new EncryptionException(sprintf('NIP-04 plaintext must be at most %d bytes', self::MAX_PLAINTEXT_LENGTH));
        }

        // Deliberate: decrypt refuses a plaintext that is not UTF-8, so encrypt never seals one — see ADR-0116
        if (!mb_check_encoding($plaintext, 'UTF-8')) {
            throw new EncryptionException('NIP-04 plaintext must be UTF-8');
        }

        $iv = $this->randomBytes->bytes(self::IV_LENGTH);

        $ciphertext = $sharedSecret->expose(static function (string $key) use ($plaintext, $iv): string {
            $ct = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            if (false === $ct) {
                throw new EncryptionException('NIP-04 encryption failed');
            }

            return $ct;
        });

        return base64_encode($ciphertext).self::IV_SEPARATOR.base64_encode($iv);
    }

    // Deliberate: every rejection reports one message so the boundary does not rank one malformed payload above another; this narrows an information leak and does NOT close the padding oracle, which is inherent to unauthenticated CBC — see ADR-0118
    #[Override]
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use Nip44Cipher instead')]
    public function decrypt(string $payload, SecretKeyMaterial $sharedSecret): string
    {
        $payloadLength = strlen($payload);

        if ($payloadLength < self::MIN_PAYLOAD_LENGTH || $payloadLength > self::MAX_PAYLOAD_LENGTH) {
            throw new EncryptionException(self::DECRYPTION_FAILED);
        }

        $separatorPosition = strpos($payload, self::IV_SEPARATOR);

        if (false === $separatorPosition) {
            throw new EncryptionException(self::DECRYPTION_FAILED);
        }

        $ciphertext = Base64Codec::tryDecodeCanonical(substr($payload, 0, $separatorPosition));
        $iv = Base64Codec::tryDecodeCanonical(substr($payload, $separatorPosition + strlen(self::IV_SEPARATOR)));

        if (null === $ciphertext || null === $iv || self::IV_LENGTH !== strlen($iv)) {
            throw new EncryptionException(self::DECRYPTION_FAILED);
        }

        return $sharedSecret->expose(static function (string $key) use ($ciphertext, $iv): string {
            $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

            if (false === $plaintext) {
                throw new EncryptionException(self::DECRYPTION_FAILED);
            }

            // Deliberate: a plaintext that is not UTF-8 is refused as a decryption failure, and a byte order mark is kept — see ADR-0116
            if (!mb_check_encoding($plaintext, 'UTF-8')) {
                sodium_memzero($plaintext);

                throw new EncryptionException(self::DECRYPTION_FAILED);
            }

            return $plaintext;
        });
    }
}
