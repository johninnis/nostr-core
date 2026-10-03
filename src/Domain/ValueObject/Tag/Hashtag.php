<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\IdentityKeyedInterface;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class Hashtag implements Stringable, IdentityKeyedInterface
{
    private function __construct(private string $value)
    {
    }

    // Deliberate: lowercased only — NIP-24 states no trimming rule and no leading-hash rule, so this invents neither — see ADR-0068
    public static function tryFromString(mixed $value): ?self
    {
        return is_string($value) && '' !== $value && mb_check_encoding($value, 'UTF-8') ? new self(mb_strtolower($value)) : null;
    }

    public static function fromString(string $value): self
    {
        return self::tryFromString($value) ?? throw new InvalidArgumentException('A hashtag is non-empty UTF-8 text');
    }

    #[Override]
    public function identityKey(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
