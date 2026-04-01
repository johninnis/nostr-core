<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use InvalidArgumentException;

final readonly class SubscriptionId
{
    public function __construct(private string $id)
    {
        if ('' === $this->id) {
            throw new InvalidArgumentException('Subscription ID cannot be empty');
        }

        if (strlen($this->id) > 64) {
            throw new InvalidArgumentException('Subscription ID cannot exceed 64 characters');
        }
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public static function short(): self
    {
        return new self(bin2hex(random_bytes(4)));
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
