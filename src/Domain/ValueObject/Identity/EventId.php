<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Exception;
use Innis\Nostr\Core\Infrastructure\Service\Bech32Codec;

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
        return Bech32Codec::encodeNote($this->id);
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
            return self::fromHex(Bech32Codec::decodeToHex($bech32));
        } catch (Exception) {
            return null;
        }
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
