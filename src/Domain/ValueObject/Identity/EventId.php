<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use Override;
use Stringable;

// Deliberate: kept a separate type, not folded into a shared base, so equals() cannot accept a sibling identity — see ADR-0004
final readonly class EventId implements Stringable
{
    public const int BYTE_LENGTH = 32;

    private function __construct(private string $id)
    {
    }

    public function toHex(): string
    {
        return $this->id;
    }

    public function toBytes(): string
    {
        return HexCodec::decode($this->id);
    }

    public function toBech32(): string
    {
        return Bech32Codec::encode('note', $this->toBytes());
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
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

        return new self(HexCodec::encode($bytes));
    }

    public static function fromBech32(string $bech32): ?self
    {
        $bytes = Bech32Codec::decodeWithHrp($bech32, 'note');

        return null === $bytes ? null : self::fromBytes($bytes);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
