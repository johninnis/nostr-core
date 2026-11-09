<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use InvalidArgumentException;
use Override;
use Stringable;

final readonly class HttpMethod implements Stringable
{
    private const string RFC_9110_TOKEN = "/^[!#$%&'*+\\-.^_`|~0-9A-Za-z]+$/D";

    /**
     * @param non-empty-string $method
     */
    private function __construct(private string $method)
    {
    }

    // Deliberate: the empty string names no method, so a host that failed to read its request's method cannot match a signer's empty method tag — see nostr-adrs ADR-0130
    public static function tryFromString(mixed $method): ?self
    {
        return is_string($method) && '' !== $method && 1 === preg_match(self::RFC_9110_TOKEN, $method) ? new self($method) : null;
    }

    public static function fromString(string $method): self
    {
        return self::tryFromString($method) ?? throw new InvalidArgumentException('An HTTP method is an RFC 9110 token: one or more token characters, with case kept');
    }

    public function equals(self $other): bool
    {
        return $this->method === $other->method;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->method;
    }
}
