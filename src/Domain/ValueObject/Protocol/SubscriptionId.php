<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Override;
use Stringable;

final readonly class SubscriptionId implements Stringable
{
    private const int MAX_LENGTH = 64;
    // Deliberate: printable-ASCII only, stricter than NIP-01's "arbitrary string", to keep the correlation handle byte-clean — see ADR-0053
    private const string ALLOWED_PATTERN = '/^[\x21-\x7E]+$/D';

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

        if (strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        if (!preg_match(self::ALLOWED_PATTERN, $value)) {
            return null;
        }

        return new self($value);
    }

    // Deliberate: reads the entropy source directly, not via an injected port; no random-dependent output under test — see ADR-0018
    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    // Deliberate: reads the entropy source directly, not via an injected port; no random-dependent output under test — see ADR-0018
    public static function short(): self
    {
        return new self(bin2hex(random_bytes(4)));
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
