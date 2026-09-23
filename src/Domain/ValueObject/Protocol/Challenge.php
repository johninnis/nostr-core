<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use InvalidArgumentException;
use Override;
use Stringable;

final readonly class Challenge implements Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function tryFromString(mixed $value): ?self
    {
        return is_string($value) && '' !== $value ? new self($value) : null;
    }

    public static function fromString(string $value): self
    {
        return self::tryFromString($value) ?? throw new InvalidArgumentException('An AUTH challenge cannot be empty');
    }

    // Deliberate: constant-time, because this is the secret a client proves it was told — see ADR-0070
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    // Deliberate: the only way out is the string cast, with no getValue() beside it, so a challenge cannot be read without the reader saying it wants the raw text — see ADR-0070
    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
