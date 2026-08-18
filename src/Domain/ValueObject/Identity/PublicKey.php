<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use Override;
use Stringable;

final readonly class PublicKey implements Stringable
{
    public const string BECH32_HRP = 'npub';

    public const int BYTE_LENGTH = 32;

    /**
     * @param non-empty-string $key
     */
    private function __construct(private string $key)
    {
    }

    /**
     * @return non-empty-string
     */
    public function toHex(): string
    {
        return $this->key;
    }

    public function toBytes(): string
    {
        return HexCodec::decode($this->key);
    }

    /**
     * @return non-empty-string
     */
    public function toBech32(): string
    {
        return Bech32Codec::encode(self::BECH32_HRP, $this->toBytes());
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    // Deliberate: validates the 32-byte shape only, not curve membership; verify and ECDH reject a non-point where the curve maths lives — see ADR-0056
    public static function tryFromHex(string $hex): ?self
    {
        $canonical = HexCodec::tryCanonical($hex, self::BYTE_LENGTH);

        return null === $canonical ? null : new self($canonical);
    }

    public static function tryFromBytes(string $bytes): ?self
    {
        if (self::BYTE_LENGTH !== strlen($bytes)) {
            return null;
        }

        return new self(HexCodec::encode($bytes));
    }

    public static function tryFromBech32(string $bech32): ?self
    {
        $bytes = Bech32Codec::decodeWithHrp($bech32, self::BECH32_HRP);

        return null === $bytes ? null : self::tryFromBytes($bytes);
    }

    public static function tryFromNpubOrHex(string $value): ?self
    {
        return str_starts_with($value, 'npub') ? self::tryFromBech32($value) : self::tryFromHex($value);
    }

    /**
     * @return non-empty-string
     */
    #[Override]
    public function __toString(): string
    {
        return $this->key;
    }
}
