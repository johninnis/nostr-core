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

    public function equals(Signature $other): bool
    {
        return $this->signature === $other->signature;
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{128}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }

    public function __toString(): string
    {
        return $this->signature;
    }
}
