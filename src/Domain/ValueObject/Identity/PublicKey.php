<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Exception;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;

final readonly class PublicKey
{
    public const HEX_LENGTH = 64;

    private function __construct(private string $key)
    {
    }

    public function toHex(): string
    {
        return $this->key;
    }

    public function toBech32(): string
    {
        return Bech32Codec::encode('npub', Bech32Codec::hexToBytes($this->key));
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }

    public static function fromBech32(string $bech32): ?self
    {
        if (!str_starts_with($bech32, 'npub1')) {
            return null;
        }

        try {
            $decoded = Bech32Codec::decode($bech32);

            return new self(Bech32Codec::bytesToHex($decoded['data']));
        } catch (Exception) {
            return null;
        }
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
