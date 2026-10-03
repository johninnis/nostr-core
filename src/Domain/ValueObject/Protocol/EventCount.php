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
    }

    public static function tryFrom(int $count, bool $approximate): ?self
    {
        return $count < 0 ? null : new self($count, $approximate);
    }

    public static function exact(int $count): self
    {
        return self::tryFrom($count, false) ?? throw new InvalidArgumentException('An event count cannot be negative');
    }

    public static function approximate(int $count): self
    {
        return self::tryFrom($count, true) ?? throw new InvalidArgumentException('An event count cannot be negative');
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
