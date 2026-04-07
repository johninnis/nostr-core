<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

final readonly class Signature
{
    private function __construct(private string $signature)
    {
    }

    public function toHex(): string
    {
        return $this->signature;
    }

    public function equals(self $other): bool
    {
        return $this->signature === $other->signature;
    }

    public static function fromHex(string $hex): ?self
    {
        $length = \strlen($hex);

        if ($length < 126 || $length > 128 || !preg_match('/^[a-f0-9]+$/', $hex)) {
            return null;
        }

        return new self(str_pad($hex, 128, '0', STR_PAD_LEFT));
    }

    public function __toString(): string
    {
        return $this->signature;
    }
}
