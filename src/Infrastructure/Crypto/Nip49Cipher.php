<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Closure;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\Nip49DecryptionFailedException;
use Innis\Nostr\Core\Domain\Exception\Nip49WorkFactorRefusedException;
use Innis\Nostr\Core\Domain\Service\Nip49EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip49WorkFactor;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use InvalidArgumentException;
use Normalizer;
use Override;

final readonly class Nip49Cipher implements Nip49EncryptionInterface
{
    public function __construct(
        private Nip49Scrypt $scrypt,
        private RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator(),
        private Nip49WorkFactor $workFactor = new Nip49WorkFactor(),
    ) {
    }

    public static function create(
        Nip49WorkFactor $workFactor = new Nip49WorkFactor(),
        RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator(),
    ): self {
        // Deliberate: probes for libsodium scrypt here, never in __construct; the constructor takes an injected scrypt for DI and tests — see ADR-0041
        return new self(Nip49Scrypt::create(), $randomBytes, $workFactor);
    }

    /**
     * @param Closure(): string $passwordProvider
     */
    #[Override]
    public function encrypt(
        PrivateKey $privateKey,
        Closure $passwordProvider,
        KeySecurityByte $keySecurity = KeySecurityByte::Untracked,
    ): Ncryptsec {
        $logN = $this->workFactor->getEncryptLogN();
        $salt = $this->randomBytes->bytes(Ncryptsec::SALT_LENGTH);
        $nonce = $this->randomBytes->bytes(Ncryptsec::NONCE_LENGTH);
        $derivedKey = $this->deriveKey($passwordProvider, $salt, $logN);

        try {
            $aeadOutput = $privateKey->expose(
                static fn (string $nsecBytes): string => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    $nsecBytes,
                    chr($keySecurity->value),
                    $nonce,
                    $derivedKey,
                )
            );
        } finally {
            sodium_memzero($derivedKey);
        }

        return Ncryptsec::create($logN, $salt, $nonce, $keySecurity, $aeadOutput);
    }

    /**
     * @param Closure(): string $passwordProvider
     */
    #[Override]
    public function decrypt(Ncryptsec $ncryptsec, Closure $passwordProvider): PrivateKey
    {
        $logN = $ncryptsec->getLogN();
        if (!$this->workFactor->admitsForDecryption($logN)) {
            throw new Nip49WorkFactorRefusedException($logN);
        }

        $derivedKey = $this->deriveKey($passwordProvider, $ncryptsec->getSalt(), $logN);

        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ncryptsec->getAeadCiphertextAndTag(),
                chr($ncryptsec->getKeySecurity()->value),
                $ncryptsec->getNonce(),
                $derivedKey,
            );
        } finally {
            sodium_memzero($derivedKey);
        }

        if (false === $plaintext) {
            throw new Nip49DecryptionFailedException();
        }

        try {
            return PrivateKey::tryFromBytes($plaintext) ?? throw new Nip49DecryptionFailedException();
        } finally {
            sodium_memzero($plaintext);
        }
    }

    /**
     * @param Closure(): string $passwordProvider
     */
    private function deriveKey(Closure $passwordProvider, string $salt, int $logN): string
    {
        $revealed = $this->revealPassword($passwordProvider);

        try {
            $normalised = Normalizer::normalize($revealed, Normalizer::FORM_KC);
            if (false === $normalised) {
                throw new InvalidArgumentException('Password could not be NFKC-normalised');
            }

            try {
                return $this->scrypt->derive($normalised, $salt, $logN);
            } finally {
                sodium_memzero($normalised);
            }
        } finally {
            sodium_memzero($revealed);
        }
    }

    /**
     * @param Closure(): string $passwordProvider
     */
    // Deliberate: XORing against zeros forces a fresh buffer this adapter solely owns, so the caller's sodium_memzero lands on it instead of separating a throwaway; returning the provider's own string aliases whatever the caller still holds and makes the wipe a no-op — the same copy-on-write trap SecretKeyMaterial::expose avoids, see ADR-0028
    private function revealPassword(Closure $passwordProvider): string
    {
        $revealed = $passwordProvider();

        return $revealed ^ str_repeat("\0", strlen($revealed));
    }
}
