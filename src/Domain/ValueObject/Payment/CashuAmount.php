<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use InvalidArgumentException;

final readonly class CashuAmount
{
    private function __construct(
        private int $amount,
        private string $unit,
    ) {
    }

    public static function from(int $amount, string $unit): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException(sprintf('A Cashu amount is a whole number of its unit, zero or more, not %d', $amount));
        }

        return new self($amount, $unit);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->unit === $other->unit;
    }
}
