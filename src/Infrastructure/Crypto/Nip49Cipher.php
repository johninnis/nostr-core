<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Closure;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\Nip49DecryptionFailedException;
use Innis\Nostr\Core\Domain\Service\Nip49EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use InvalidArgumentException;
use Normalizer;
use Override;

final class Nip49Cipher implements Nip49EncryptionInterface
{
    // Deliberate: encrypt floors at 16 so no weak-KDF ncryptsec is minted; decrypt accepts lower for interop — see ADR-0030
    private const int ENCRYPT_LOG_N_MIN = 16;
    private const int LOG_N_MIN = 1;
    private const int LOG_N_MAX = 22;

    public function __construct(
        private readonly Nip49Scrypt $scrypt = new Nip49Scrypt(null),
        private readonly RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator(),
        private readonly int $maxDecryptLogN = self::LOG_N_MAX,
    ) {
        if ($maxDecryptLogN < self::LOG_N_MIN || $maxDecryptLogN > self::LOG_N_MAX) {
            throw new InvalidArgumentException(sprintf('maxDecryptLogN must be between %d and %d', self::LOG_N_MIN, self::LOG_N_MAX));
        }
    }

    public static function create(
        ?RandomBytesGeneratorInterface $randomBytes = null,
        int $maxDecryptLogN = self::LOG_N_MAX,
    ): self {
        // Deliberate: probes for libsodium scrypt here, never in __construct; the bare constructor stays on a non-FFI scrypt for DI and tests — see ADR-0041
        return new self(Nip49Scrypt::create(), $randomBytes ?? new NativeRandomBytesGenerator(), $maxDecryptLogN);
    }

    #[Override]
    public function encrypt(
        PrivateKey $privateKey,
        Closure $passwordProvider,
        int $logN = self::ENCRYPT_LOG_N_MIN,
        KeySecurityByte $keySecurity = KeySecurityByte::Unknown,
    ): Ncryptsec {
        if ($logN < self::ENCRYPT_LOG_N_MIN || $logN > self::LOG_N_MAX) {
            throw new InvalidArgumentException(sprintf('logN must be between %d and %d', self::ENCRYPT_LOG_N_MIN, self::LOG_N_MAX));
        }

        $salt = $this->randomBytes->bytes(Ncryptsec::SALT_LENGTH);
        $nonce = $this->randomBytes->bytes(Ncryptsec::NONCE_LENGTH);

        $aeadOutput = $this->withDerivedKey(
            $passwordProvider,
            $salt,
            $logN,
            static fn (string $derivedKey): string => $privateKey->expose(
                static fn (string $nsecBytes): string => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    $nsecBytes,
                    chr($keySecurity->value),
                    $nonce,
                    $derivedKey,
                )
            ),
        );

        return Ncryptsec::create($logN, $salt, $nonce, $keySecurity, $aeadOutput);
    }

    #[Override]
    public function decrypt(Ncryptsec $ncryptsec, Closure $passwordProvider): PrivateKey
    {
        $logN = $ncryptsec->getLogN();
        if ($logN < self::LOG_N_MIN || $logN > $this->maxDecryptLogN) {
            throw new Nip49DecryptionFailedException();
        }

        try {
            $keySecurity = KeySecurityByte::fromByte($ncryptsec->getKeySecurityByteRaw());
        } catch (InvalidArgumentException) {
            throw new Nip49DecryptionFailedException();
        }

        $plaintext = $this->withDerivedKey(
            $passwordProvider,
            $ncryptsec->getSalt(),
            $logN,
            static fn (string $derivedKey): string|false => sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ncryptsec->getAeadCiphertextAndTag(),
                chr($keySecurity->value),
                $ncryptsec->getNonce(),
                $derivedKey,
            ),
        );

        if (false === $plaintext) {
            throw new Nip49DecryptionFailedException();
        }

        try {
            return PrivateKey::fromBytes($plaintext) ?? throw new Nip49DecryptionFailedException();
        } finally {
            sodium_memzero($plaintext);
        }
    }

    /**
     * @template T
     *
     * @param Closure(): string  $passwordProvider
     * @param Closure(string): T $use
     *
     * @return T
     */
    private function withDerivedKey(Closure $passwordProvider, string $salt, int $logN, Closure $use): mixed
    {
        $revealed = $this->revealPassword($passwordProvider);

        try {
            $normalised = Normalizer::normalize($revealed, Normalizer::FORM_KC);
            if (false === $normalised) {
                throw new InvalidArgumentException('Password could not be NFKC-normalised');
            }

            try {
                $derivedKey = $this->scrypt->derive($normalised, $salt, $logN);

                try {
                    return $use($derivedKey);
                } finally {
                    sodium_memzero($derivedKey);
                }
            } finally {
                sodium_memzero($normalised);
            }
        } finally {
            sodium_memzero($revealed);
        }
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
