<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Exception\SecretKeyMaterialZeroedException;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use InvalidArgumentException;
use SensitiveParameter;
use SensitiveParameterValue;

// Deliberate: the bytes sit in a mutable cell inside SensitiveParameterValue, PHP's own opaque holder, so var_dump, print_r, var_export, an array cast and serialize all see nothing, and zero() can still wipe them in place — see ADR-0148
final readonly class SecretKeyMaterial
{
    public const int BYTE_LENGTH = 32;

    private SensitiveParameterValue $opaqueCell;

    private function __construct(#[SensitiveParameter] string $bytes)
    {
        $this->opaqueCell = new SensitiveParameterValue(new SecretCell($bytes));
    }

    public static function tryFromBytes(#[SensitiveParameter] string $bytes): ?self
    {
        return self::BYTE_LENGTH === strlen($bytes) ? new self($bytes) : null;
    }

    public static function fromBytes(#[SensitiveParameter] string $bytes): self
    {
        return self::tryFromBytes($bytes) ?? throw new InvalidArgumentException(sprintf('Secret key material must be %d bytes', self::BYTE_LENGTH));
    }

    // Deliberate: reads random_bytes directly, not via an injected port; no random-dependent output under test — see ADR-0018
    public static function random(): self
    {
        return self::fromBytes(random_bytes(self::BYTE_LENGTH));
    }

    public static function tryFromHex(#[SensitiveParameter] string $hex): ?self
    {
        $canonical = HexCodec::tryCanonical($hex, self::BYTE_LENGTH);

        // Deliberate: sodium's hex codec runs in constant time, so secret hex is read without a branch on its digits — see ADR-0120
        return null === $canonical ? null : self::tryFromBytes(sodium_hex2bin($canonical));
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
        $cell = $this->cell();

        if (null === $cell->bytes) {
            throw new SecretKeyMaterialZeroedException();
        }

        // Deliberate: XOR forces a fresh, non-copy-on-write buffer so sodium_memzero wipes the exposed bytes, not a throwaway; do not reduce to the stored bytes — see ADR-0150
        $exposed = $cell->bytes ^ str_repeat("\0", self::BYTE_LENGTH);

        try {
            return $fn($exposed);
        } finally {
            sodium_memzero($exposed);
        }
    }

    public function zero(): void
    {
        $cell = $this->cell();

        if (null !== $cell->bytes) {
            sodium_memzero($cell->bytes);
        }
    }

    public function isZeroed(): bool
    {
        return null === $this->cell()->bytes;
    }

    public function __destruct()
    {
        $this->zero();
    }

    private function cell(): SecretCell
    {
        return $this->opaqueCell->getValue();
    }
}
