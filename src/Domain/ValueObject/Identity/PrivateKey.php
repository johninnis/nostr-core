<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Exception\SerialisationException;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use SensitiveParameter;

final readonly class PrivateKey
{
    public const string BECH32_HRP = 'nsec';

    private const string CURVE_ORDER_HEX = 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';

    private function __construct(private SecretKeyMaterial $material)
    {
    }

    public static function tryFromHex(#[SensitiveParameter] string $hex): ?self
    {
        return self::fromValidatedMaterial(SecretKeyMaterial::tryFromHex($hex));
    }

    public static function tryFromBech32(#[SensitiveParameter] string $bech32): ?self
    {
        $bytes = Bech32Codec::decodeWithHrp($bech32, self::BECH32_HRP);

        return null === $bytes ? null : self::tryFromBytes($bytes);
    }

    public static function tryFromBytes(#[SensitiveParameter] string $bytes): ?self
    {
        return self::fromValidatedMaterial(SecretKeyMaterial::tryFromBytes($bytes));
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

    // Deliberate: sodium_compare reads its operands as little-endian numbers in constant time, so the big-endian scalar and order are reversed and the bounds 0 < k < n checked with it rather than compared as hex, which branches on the secret's digits — see ADR-0120
    private static function isWithinCurveOrder(string $bytes): bool
    {
        $littleEndian = strrev($bytes);

        try {
            return sodium_compare($littleEndian, str_repeat("\x00", strlen($bytes))) > 0
                && sodium_compare($littleEndian, strrev(sodium_hex2bin(self::CURVE_ORDER_HEX))) < 0;
        } finally {
            sodium_memzero($littleEndian);
        }
    }

    public function toHex(): string
    {
        return $this->material->expose(sodium_bin2hex(...));
    }

    public function toBech32(): string
    {
        return $this->material->expose(static fn (string $bytes): string => Bech32Codec::encode(self::BECH32_HRP, $bytes)
            ?? throw new SerialisationException('A 32-byte private key always fits a bech32 string'));
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
