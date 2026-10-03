<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use InvalidArgumentException;

final readonly class Nip49WorkFactor
{
    // Deliberate: encrypt floors at 16 so no weak-KDF ncryptsec is minted; decrypt accepts lower for interop, up to a ceiling that bounds scrypt memory — see ADR-0114
    private const int ENCRYPT_LOG_N_MIN = 16;
    private const int LOG_N_MIN = 1;
    private const int LOG_N_MAX = 22;

    public function __construct(
        private int $encryptLogN = self::ENCRYPT_LOG_N_MIN,
        private int $maxDecryptLogN = self::LOG_N_MAX,
    ) {
        if ($encryptLogN < self::ENCRYPT_LOG_N_MIN || $encryptLogN > self::LOG_N_MAX) {
            throw new InvalidArgumentException(sprintf('logN must be between %d and %d', self::ENCRYPT_LOG_N_MIN, self::LOG_N_MAX));
        }
        if ($maxDecryptLogN < self::LOG_N_MIN || $maxDecryptLogN > self::LOG_N_MAX) {
            throw new InvalidArgumentException(sprintf('maxDecryptLogN must be between %d and %d', self::LOG_N_MIN, self::LOG_N_MAX));
        }
        if ($maxDecryptLogN < $encryptLogN) {
            throw new InvalidArgumentException(sprintf('maxDecryptLogN %d is below encryptLogN %d, so this cipher would refuse what it encrypts', $maxDecryptLogN, $encryptLogN));
        }
    }

    public function getEncryptLogN(): int
    {
        return $this->encryptLogN;
    }

    public function admitsForDecryption(int $logN): bool
    {
        return $logN >= self::LOG_N_MIN && $logN <= $this->maxDecryptLogN;
    }
}
