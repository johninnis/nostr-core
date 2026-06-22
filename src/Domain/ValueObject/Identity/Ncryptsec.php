<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
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

    private function __construct(
        private string $bech32,
        private string $payload,
    ) {
    }

    public static function fromString(string $bech32): ?self
    {
        $payload = Bech32Codec::decodeWithHrp($bech32, self::HRP);
        if (null === $payload) {
            return null;
        }

        if (self::PAYLOAD_LENGTH !== strlen($payload)) {
            return null;
        }

        if (self::VERSION_BYTE !== ord($payload[self::VERSION_OFFSET])) {
            return null;
        }

        return new self($bech32, $payload);
    }

    public static function fromFields(
        int $logN,
        string $salt,
        string $nonce,
        KeySecurityByte $keySecurity,
        string $aeadOutput,
    ): self {
        if ($logN < 0 || $logN > 255) {
            throw new InvalidArgumentException('logN must fit in a single byte');
        }

        if (self::SALT_LENGTH !== strlen($salt)) {
            throw new InvalidArgumentException(sprintf('Salt must be %d bytes', self::SALT_LENGTH));
        }

        if (self::NONCE_LENGTH !== strlen($nonce)) {
            throw new InvalidArgumentException(sprintf('Nonce must be %d bytes', self::NONCE_LENGTH));
        }

        if (self::AEAD_OUTPUT_LENGTH !== strlen($aeadOutput)) {
            throw new InvalidArgumentException(sprintf('AEAD output must be %d bytes', self::AEAD_OUTPUT_LENGTH));
        }

        $payload = chr(self::VERSION_BYTE)
            .chr($logN)
            .$salt
            .$nonce
            .chr($keySecurity->value)
            .$aeadOutput;

        return new self(Bech32Codec::encode(self::HRP, $payload), $payload);
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

    public function getKeySecurityByteRaw(): int
    {
        return ord($this->payload[self::KEY_SECURITY_OFFSET]);
    }

    public function getAeadCiphertextAndTag(): string
    {
        return substr($this->payload, self::CIPHERTEXT_OFFSET, self::AEAD_OUTPUT_LENGTH);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->bech32;
    }
}
