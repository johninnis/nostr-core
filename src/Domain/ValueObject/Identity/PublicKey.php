<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use Override;
use Stringable;

// Deliberate: kept a separate type, not folded into a shared base, so equals() cannot accept a sibling identity — see ADR-0004
final readonly class PublicKey implements Stringable
{
    public const int BYTE_LENGTH = 32;

    private function __construct(private string $key)
    {
    }

    public function toHex(): string
    {
        return $this->key;
    }

    public function toBytes(): string
    {
        return HexCodec::toBytes($this->key);
    }

    public function toBech32(): string
    {
        return Bech32Codec::encode('npub', $this->toBytes());
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public static function fromHex(string $hex): ?self
    {
        if (!HexCodec::isValid($hex, self::BYTE_LENGTH)) {
            return null;
        }

        return new self($hex);
    }

    public static function fromBytes(string $bytes): ?self
    {
        if (self::BYTE_LENGTH !== strlen($bytes)) {
            return null;
        }

        return new self(HexCodec::fromBytes($bytes));
    }

    public static function fromBech32(string $bech32): ?self
    {
        $bytes = Bech32Codec::decodeWithHrp($bech32, 'npub');

        return null === $bytes ? null : self::fromBytes($bytes);
    }

    public static function fromNpubOrHex(string $value): ?self
    {
        return str_starts_with($value, 'npub') ? self::fromBech32($value) : self::fromHex($value);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->key;
    }
}
