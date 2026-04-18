<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\InvalidBech32Exception;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use InvalidArgumentException;
use Stringable;

final readonly class Ncryptsec implements Stringable
{
    public const HRP = 'ncryptsec';
    public const PAYLOAD_LENGTH = 91;
    public const VERSION_BYTE = 0x02;
    public const SALT_LENGTH = 16;
    public const NONCE_LENGTH = 24;
    public const AEAD_OUTPUT_LENGTH = 48;

    private const VERSION_OFFSET = 0;
    private const LOG_N_OFFSET = 1;
    private const SALT_OFFSET = 2;
    private const NONCE_OFFSET = 18;
    private const KEY_SECURITY_OFFSET = 42;
    private const CIPHERTEXT_OFFSET = 43;

    private function __construct(
        private string $bech32,
        private string $payload,
    ) {
    }

    public static function fromString(string $bech32): ?self
    {
        if (!str_starts_with($bech32, self::HRP.'1')) {
            return null;
        }

        try {
            $decoded = Bech32Codec::decode($bech32);
        } catch (InvalidBech32Exception) {
            return null;
        }

        if (self::HRP !== $decoded['hrp']) {
            return null;
        }

        if (self::PAYLOAD_LENGTH !== count($decoded['data'])) {
            return null;
        }

        $payload = pack('C*', ...$decoded['data']);

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

        $byteValues = unpack('C*', $payload);
        assert(false !== $byteValues);

        $bech32 = Bech32Codec::encode(self::HRP, array_values($byteValues));

        return new self($bech32, $payload);
    }

    public function logN(): int
    {
        return ord($this->payload[self::LOG_N_OFFSET]);
    }

    public function salt(): string
    {
        return substr($this->payload, self::SALT_OFFSET, self::SALT_LENGTH);
    }

    public function nonce(): string
    {
        return substr($this->payload, self::NONCE_OFFSET, self::NONCE_LENGTH);
    }

    public function keySecurityByteRaw(): int
    {
        return ord($this->payload[self::KEY_SECURITY_OFFSET]);
    }

    public function aeadCiphertextAndTag(): string
    {
        return substr($this->payload, self::CIPHERTEXT_OFFSET, self::AEAD_OUTPUT_LENGTH);
    }

    public function __toString(): string
    {
        return $this->bech32;
    }
}
