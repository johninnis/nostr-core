<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use Closure;
use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use InvalidArgumentException;

final class SecretKeyMaterial
{
    public const BYTE_LENGTH = 32;

    private ?string $bytes;

    private function __construct(string $bytes)
    {
        $this->bytes = $bytes;
    }

    public static function fromBytes(string $bytes): self
    {
        if (self::BYTE_LENGTH !== strlen($bytes)) {
            throw new InvalidArgumentException(sprintf('Secret key material must be %d bytes', self::BYTE_LENGTH));
        }

        return new self($bytes);
    }

    public static function random(): self
    {
        return new self(random_bytes(self::BYTE_LENGTH));
    }

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
