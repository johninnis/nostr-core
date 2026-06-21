<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use Closure;
use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use InvalidArgumentException;

final class SecretKeyMaterial
{
    public const int BYTE_LENGTH = 32;

    private ?string $bytes;

    public function __construct(string $bytes)
    {
        if (self::BYTE_LENGTH !== strlen($bytes)) {
            throw new InvalidArgumentException(sprintf('Secret key material must be %d bytes', self::BYTE_LENGTH));
        }

        $this->bytes = $bytes;
    }

    public static function random(): self
    {
        return new self(random_bytes(self::BYTE_LENGTH));
    }

    public static function fromHex(string $hex): ?self
    {
        return HexCodec::isValid($hex, self::BYTE_LENGTH) ? new self(HexCodec::toBytes($hex)) : null;
    }

    public static function fromBytes(string $bytes): ?self
    {
        return self::BYTE_LENGTH === strlen($bytes) ? new self($bytes) : null;
    }

    /**
     * @template T
     *
     * @param Closure(string): T $fn
     *
     * @return T
     */
    public function expose(Closure $fn): mixed
    {
        if (null === $this->bytes) {
            throw new SecretKeyMaterialZeroedException();
        }

        $exposed = $this->bytes;

        try {
            return $fn($exposed);
        } finally {
            sodium_memzero($exposed);
        }
    }

    public function zero(): void
    {
        if (null === $this->bytes) {
            return;
        }

        sodium_memzero($this->bytes);
        $this->bytes = null;
    }

    public function isZeroed(): bool
    {
        return null === $this->bytes;
    }

    public function __destruct()
    {
        $this->zero();
    }
}
