<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use Override;
use Stringable;

final readonly class PublicKey implements Stringable
{
    public const int BYTE_LENGTH = 32;
    public const int HEX_LENGTH = self::BYTE_LENGTH * 2;

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
        $decoded = Bech32Codec::decode($bech32);
        if (null === $decoded || 'npub' !== $decoded['hrp']) {
            return null;
        }

        return self::fromBytes($decoded['data']);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->key;
    }
}
