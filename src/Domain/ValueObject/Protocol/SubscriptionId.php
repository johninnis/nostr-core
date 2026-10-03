<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Override;
use Stringable;

final readonly class SubscriptionId implements Stringable
{
    private const int MAX_LENGTH = 64;

    private function __construct(private string $id)
    {
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public static function tryFromString(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        if ('' === $value) {
            return null;
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        // Deliberate: NIP-01's 64 "chars" are Unicode characters of the JSON string, not UTF-8 bytes, and any character is allowed — see ADR-0084
        if (mb_strlen($value, 'UTF-8') > self::MAX_LENGTH) {
            return null;
        }

        return new self($value);
    }

    // Deliberate: reads the entropy source directly, not via an injected port; no random-dependent output under test — see ADR-0018
    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
