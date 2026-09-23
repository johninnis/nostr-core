<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use InvalidArgumentException;

final readonly class EventCount
{
    private function __construct(
        private int $count,
        private bool $approximate,
    ) {
        if ($count < 0) {
            throw new InvalidArgumentException('An event count cannot be negative');
        }
    }

    public static function exact(int $count): self
    {
        return new self($count, false);
    }

    public static function approximate(int $count): self
    {
        return new self($count, true);
    }

    public function toInt(): int
    {
        return $this->count;
    }

    public function isApproximate(): bool
    {
        return $this->approximate;
    }
}
