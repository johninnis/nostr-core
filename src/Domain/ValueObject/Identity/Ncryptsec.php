<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\SerialisationException;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class Ncryptsec implements Stringable
{
    public const string HRP = 'ncryptsec';
    public const int PAYLOAD_LENGTH = 91;
    public const int VERSION_BYTE = 0x02;
    public const int SALT_LENGTH = 16;
    public const int NONCE_LENGTH = 24;
    public const int AEAD_OUTPUT_LENGTH = 48;

    private const int VERSION_OFFSET = 0;
    private const int LOG_N_OFFSET = 1;
    private const int SALT_OFFSET = 2;
    private const int NONCE_OFFSET = 18;
    private const int KEY_SECURITY_OFFSET = 42;
    private const int CIPHERTEXT_OFFSET = 43;

    private function __construct(private string $payload)
    {
    }

    public static function tryFromString(string $bech32): ?self
    {
        $payload = Bech32Codec::decodeWithHrp($bech32, self::HRP);
        if (null === $payload || self::PAYLOAD_LENGTH !== strlen($payload)) {
            return null;
        }
        if (self::VERSION_BYTE !== ord($payload[self::VERSION_OFFSET])) {
            return null;
        }
        // Deliberate: an unrecognised key-security byte is refused here, never mapped to Untracked, so a tampered byte cannot pass for a valid one — see ADR-0119
        $keySecurity = KeySecurityByte::tryFrom(ord($payload[self::KEY_SECURITY_OFFSET]));
        if (null === $keySecurity) {
            return null;
        }

        return self::tryFrom(
            ord($payload[self::LOG_N_OFFSET]),
            substr($payload, self::SALT_OFFSET, self::SALT_LENGTH),
            substr($payload, self::NONCE_OFFSET, self::NONCE_LENGTH),
            $keySecurity,
            substr($payload, self::CIPHERTEXT_OFFSET, self::AEAD_OUTPUT_LENGTH),
        );
    }

    public static function tryFrom(
        int $logN,
        string $salt,
        string $nonce,
        KeySecurityByte $keySecurity,
        string $aeadOutput,
    ): ?self {
        $fieldsFit = $logN >= 0 && $logN <= 255
            && self::SALT_LENGTH === strlen($salt)
            && self::NONCE_LENGTH === strlen($nonce)
            && self::AEAD_OUTPUT_LENGTH === strlen($aeadOutput);

        return $fieldsFit
            ? new self(chr(self::VERSION_BYTE).chr($logN).$salt.$nonce.chr($keySecurity->value).$aeadOutput)
            : null;
    }

    public static function create(
        int $logN,
        string $salt,
        string $nonce,
        KeySecurityByte $keySecurity,
        string $aeadOutput,
    ): self {
        return self::tryFrom($logN, $salt, $nonce, $keySecurity, $aeadOutput)
            ?? throw new InvalidArgumentException(sprintf('An ncryptsec takes a logN of 0 to 255, a %d-byte salt, a %d-byte nonce and a %d-byte AEAD output', self::SALT_LENGTH, self::NONCE_LENGTH, self::AEAD_OUTPUT_LENGTH));
    }

    public function getLogN(): int
    {
        return ord($this->payload[self::LOG_N_OFFSET]);
    }

    public function getSalt(): string
    {
        return substr($this->payload, self::SALT_OFFSET, self::SALT_LENGTH);
    }

    public function getNonce(): string
    {
        return substr($this->payload, self::NONCE_OFFSET, self::NONCE_LENGTH);
    }

    public function getKeySecurity(): KeySecurityByte
    {
        return KeySecurityByte::from(ord($this->payload[self::KEY_SECURITY_OFFSET]));
    }

    public function getAeadCiphertextAndTag(): string
    {
        return substr($this->payload, self::CIPHERTEXT_OFFSET, self::AEAD_OUTPUT_LENGTH);
    }

    #[Override]
    public function __toString(): string
    {
        return Bech32Codec::encode(self::HRP, $this->payload)
            ?? throw new SerialisationException('A 91-byte ncryptsec payload always fits a bech32 string');
    }
}
