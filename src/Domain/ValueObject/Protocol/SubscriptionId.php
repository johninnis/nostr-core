<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Override;
use Stringable;

final readonly class SubscriptionId implements Stringable
{
    private const int MAX_LENGTH = 64;
    private const string ALLOWED_PATTERN = '/^[\x21-\x7E]+$/';

    private function __construct(private string $id)
    {
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public static function fromString(string $id): ?self
    {
        if ('' === $id) {
            return null;
        }

        if (strlen($id) > self::MAX_LENGTH) {
            return null;
        }

        if (!preg_match(self::ALLOWED_PATTERN, $id)) {
            return null;
        }

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

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
