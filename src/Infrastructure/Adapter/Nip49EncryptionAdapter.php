<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Adapter;

use Closure;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\Nip49DecryptionFailedException;
use Innis\Nostr\Core\Domain\Service\Nip49EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Scrypt;
use InvalidArgumentException;
use Normalizer;

final class Nip49EncryptionAdapter implements Nip49EncryptionInterface
{
    private const LOG_N_MIN = 1;
    private const LOG_N_MAX = 63;

    public function __construct(
        private readonly Nip49Scrypt $scrypt = new Nip49Scrypt(),
        private readonly RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGeneratorAdapter(),
    ) {
    }

    public function encrypt(
        PrivateKey $privateKey,
        Closure $passwordProvider,
        int $logN = 16,
        KeySecurityByte $keySecurity = KeySecurityByte::Unknown,
    ): Ncryptsec {
        if ($logN < self::LOG_N_MIN || $logN > self::LOG_N_MAX) {
            throw new InvalidArgumentException(sprintf('logN must be between %d and %d', self::LOG_N_MIN, self::LOG_N_MAX));
        }

        $salt = $this->randomBytes->bytes(Ncryptsec::SALT_LENGTH);
        $nonce = $this->randomBytes->bytes(Ncryptsec::NONCE_LENGTH);

        return $this->encryptWithSaltAndNonce($privateKey, $passwordProvider, $logN, $keySecurity, $salt, $nonce);
    }

    public function decrypt(Ncryptsec $ncryptsec, Closure $passwordProvider): PrivateKey
    {
        $logN = $ncryptsec->logN();
        if ($logN < self::LOG_N_MIN || $logN > self::LOG_N_MAX) {
            throw new Nip49DecryptionFailedException();
        }

        try {
            $keySecurity = KeySecurityByte::fromByte($ncryptsec->keySecurityByteRaw());
        } catch (InvalidArgumentException) {
            throw new Nip49DecryptionFailedException();
        }

        $revealed = $this->revealPassword($passwordProvider);

        try {
            $normalised = Normalizer::normalize($revealed, Normalizer::FORM_KC);
            if (false === $normalised) {
                throw new Nip49DecryptionFailedException();
            }

            try {
                $derivedKey = $this->scrypt->derive($normalised, $ncryptsec->salt(), $logN);

                try {
                    $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                        $ncryptsec->aeadCiphertextAndTag(),
                        chr($keySecurity->value),
                        $ncryptsec->nonce(),
                        $derivedKey,
                    );
                } finally {
                    sodium_memzero($derivedKey);
                }
            } finally {
                sodium_memzero($normalised);
            }
        } finally {
            sodium_memzero($revealed);
        }

        if (false === $plaintext || SecretKeyMaterial::BYTE_LENGTH !== strlen($plaintext)) {
            throw new Nip49DecryptionFailedException();
        }

        $privateKey = PrivateKey::fromBytes($plaintext);
        sodium_memzero($plaintext);

        return $privateKey;
    }

    private function encryptWithSaltAndNonce(
        PrivateKey $privateKey,
        Closure $passwordProvider,
        int $logN,
        KeySecurityByte $keySecurity,
        string $salt,
        string $nonce,
    ): Ncryptsec {
        $revealed = $this->revealPassword($passwordProvider);

        try {
            $normalised = Normalizer::normalize($revealed, Normalizer::FORM_KC);
            if (false === $normalised) {
                throw new InvalidArgumentException('Password could not be NFKC-normalised');
            }

            try {
                $derivedKey = $this->scrypt->derive($normalised, $salt, $logN);

                try {
                    $aeadOutput = $privateKey->expose(
                        static fn (string $nsecBytes): string => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                            $nsecBytes,
                            chr($keySecurity->value),
                            $nonce,
                            $derivedKey,
                        )
                    );
                    assert(is_string($aeadOutput));
                } finally {
                    sodium_memzero($derivedKey);
                }
            } finally {
                sodium_memzero($normalised);
            }
        } finally {
            sodium_memzero($revealed);
        }

        return Ncryptsec::fromFields($logN, $salt, $nonce, $keySecurity, $aeadOutput);
    }

    private function revealPassword(Closure $passwordProvider): string
    {
        $revealed = $passwordProvider();

        if (!is_string($revealed)) {
            throw new InvalidArgumentException('Password provider must return a string');
        }

        return $revealed;
    }
}
