<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\HexCodec;
use Override;
use Stringable;

final readonly class Signature implements Stringable
{
    public const int BYTE_LENGTH = 64;

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
        if (!HexCodec::isValid($hex, self::BYTE_LENGTH)) {
            return null;
        }

        return new self($hex);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->signature;
    }
}
