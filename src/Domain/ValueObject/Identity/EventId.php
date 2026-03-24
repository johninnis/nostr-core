<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Exception;
use nostriphant\NIP19\Bech32;

final readonly class EventId
{
    private function __construct(private string $id)
    {
    }

    public function toHex(): string
    {
        return $this->id;
    }

    public function toBech32(): string
    {
        return (string) Bech32::note($this->id);
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }

    public static function fromBech32(string $bech32): ?self
    {
        if (!str_starts_with($bech32, 'note1')) {
            return null;
        }

        try {
            $decoded = new Bech32($bech32);
            $hex = $decoded();

            return is_string($hex) ? self::fromHex($hex) : null;
        } catch (Exception) {
            return null;
        }
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
