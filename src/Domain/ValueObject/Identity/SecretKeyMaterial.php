<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use InvalidArgumentException;
use SensitiveParameter;

// Deliberate: a plain final class, not final readonly, so zero() can null the bytes field to wipe the secret — see ADR-0015
final class SecretKeyMaterial
{
    public const int BYTE_LENGTH = 32;

    private ?string $bytes;

    public function __construct(#[SensitiveParameter] string $bytes)
    {
        if (self::BYTE_LENGTH !== strlen($bytes)) {
            throw new InvalidArgumentException(sprintf('Secret key material must be %d bytes', self::BYTE_LENGTH));
        }

        $this->bytes = $bytes;
    }

    // Deliberate: reads random_bytes directly, not via an injected port; no random-dependent output under test — see ADR-0018
    public static function random(): self
    {
        return new self(random_bytes(self::BYTE_LENGTH));
    }

    public static function tryFromHex(#[SensitiveParameter] string $hex): ?self
    {
        $canonical = HexCodec::tryCanonical($hex, self::BYTE_LENGTH);

        return null === $canonical ? null : new self(HexCodec::decode($canonical));
    }

    public static function tryFromBytes(#[SensitiveParameter] string $bytes): ?self
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

        // Deliberate: XOR forces a fresh, non-copy-on-write buffer so sodium_memzero wipes the exposed bytes, not a throwaway; do not reduce to $this->bytes — see ADR-0028
        $exposed = $this->bytes ^ str_repeat("\0", self::BYTE_LENGTH);

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
