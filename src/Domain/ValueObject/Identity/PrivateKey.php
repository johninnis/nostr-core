<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\HexCodec;

// Deliberate: rejects scalars outside [1, n-1] so both signing backends agree and no degenerate key is built — see ADR-0029
final readonly class PrivateKey
{
    private const string CURVE_ORDER_HEX = 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';
    private const string ZERO_HEX = '0000000000000000000000000000000000000000000000000000000000000000';

    private function __construct(private SecretKeyMaterial $material)
    {
    }

    public static function fromHex(string $hex): ?self
    {
        return self::fromValidatedMaterial(SecretKeyMaterial::fromHex($hex));
    }

    public static function fromBech32(string $bech32): ?self
    {
        $bytes = Bech32Codec::decodeWithHrp($bech32, 'nsec');

        return null === $bytes ? null : self::fromBytes($bytes);
    }

    public static function fromBytes(string $bytes): ?self
    {
        return self::fromValidatedMaterial(SecretKeyMaterial::fromBytes($bytes));
    }

    public static function generate(): self
    {
        while (true) {
            $key = self::fromValidatedMaterial(SecretKeyMaterial::random());

            if (null !== $key) {
                return $key;
            }
        }
    }

    private static function fromValidatedMaterial(?SecretKeyMaterial $material): ?self
    {
        if (null === $material) {
            return null;
        }

        if (!$material->expose(self::isWithinCurveOrder(...))) {
            $material->zero();

            return null;
        }

        return new self($material);
    }

    private static function isWithinCurveOrder(string $bytes): bool
    {
        $hex = bin2hex($bytes);

        try {
            return self::ZERO_HEX !== $hex && strcmp($hex, self::CURVE_ORDER_HEX) < 0;
        } finally {
            sodium_memzero($hex);
        }
    }

    public function toHex(): string
    {
        return $this->material->expose(HexCodec::fromBytes(...));
    }

    public function toBech32(): string
    {
        return $this->material->expose(static fn (string $bytes): string => Bech32Codec::encode('nsec', $bytes));
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
        return $this->material->expose($fn);
    }

    public function zero(): void
    {
        $this->material->zero();
    }

    public function isZeroed(): bool
    {
        return $this->material->isZeroed();
    }
}
