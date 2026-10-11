<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\IdentityKeyedInterface;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class WebUrl implements Stringable, IdentityKeyedInterface
{
    private function __construct(private string $url)
    {
    }

    // Deliberate: checked by HttpUrl's rules but kept exactly as written, since HttpUrl's form exists only to compare and a client only opens or fetches this URL — see nostr-adrs ADR-0079 and ADR-0090
    public static function tryFromString(mixed $url): ?self
    {
        return is_string($url) && null !== HttpUrl::tryFromString($url) ? new self($url) : null;
    }

    public static function fromString(string $url): self
    {
        return self::tryFromString($url) ?? throw new InvalidArgumentException('A web URL is an http or https URL that HttpUrl gives a form, kept exactly as written');
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url;
    }

    #[Override]
    public function identityKey(): string
    {
        return $this->url;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->url;
    }
}
