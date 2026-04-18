<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\Nip49DecryptionFailedException;
use Innis\Nostr\Core\Domain\Service\Nip49EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;
use InvalidArgumentException;
use Normalizer;

final class Nip49EncryptionAdapter implements Nip49EncryptionInterface
{
    private const LOG_N_MIN = 1;
    private const LOG_N_MAX = 63;

    public function __construct(private readonly Nip49Scrypt $scrypt = new Nip49Scrypt())
    {
    }

    public function encrypt(
        PrivateKey $privateKey,
        string $password,
        int $logN = 16,
        KeySecurityByte $keySecurity = KeySecurityByte::Unknown,
    ): Ncryptsec {
        $salt = random_bytes(Ncryptsec::SALT_LENGTH);
        $nonce = random_bytes(Ncryptsec::NONCE_LENGTH);

        return $this->encryptWithSaltAndNonce($privateKey, $password, $logN, $keySecurity, $salt, $nonce);
    }

    public function decrypt(Ncryptsec $ncryptsec, string $password): PrivateKey
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

        $normalised = Normalizer::normalize($password, Normalizer::FORM_KC);
        if (false === $normalised) {
            throw new Nip49DecryptionFailedException();
        }

        $derivedKey = $this->scrypt->derive($normalised, $ncryptsec->salt(), $logN);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ncryptsec->aeadCiphertextAndTag(),
            chr($keySecurity->value),
            $ncryptsec->nonce(),
            $derivedKey,
        );

        sodium_memzero($derivedKey);
        sodium_memzero($normalised);

        if (false === $plaintext || SecretKeyMaterial::BYTE_LENGTH !== strlen($plaintext)) {
            throw new Nip49DecryptionFailedException();
        }

        $material = SecretKeyMaterial::fromBytes($plaintext);
        sodium_memzero($plaintext);

        return PrivateKey::fromMaterial($material);
    }

    public function encryptWithSaltAndNonce(
        PrivateKey $privateKey,
        string $password,
        int $logN,
        KeySecurityByte $keySecurity,
        string $salt,
        string $nonce,
    ): Ncryptsec {
        if ($logN < self::LOG_N_MIN || $logN > self::LOG_N_MAX) {
            throw new InvalidArgumentException(sprintf('logN must be between %d and %d', self::LOG_N_MIN, self::LOG_N_MAX));
        }

        $normalised = Normalizer::normalize($password, Normalizer::FORM_KC);
        if (false === $normalised) {
            throw new InvalidArgumentException('Password could not be NFKC-normalised');
        }

        $derivedKey = $this->scrypt->derive($normalised, $salt, $logN);

        $aeadOutput = $privateKey->expose(
            static fn (string $nsecBytes): string => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $nsecBytes,
                chr($keySecurity->value),
                $nonce,
                $derivedKey,
            )
        );
        assert(is_string($aeadOutput));

        sodium_memzero($derivedKey);
        sodium_memzero($normalised);

        return Ncryptsec::fromFields($logN, $salt, $nonce, $keySecurity, $aeadOutput);
    }
}
