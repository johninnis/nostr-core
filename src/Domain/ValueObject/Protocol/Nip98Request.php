<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class Nip98Request
{
    private function __construct(
        private string $url,
        private string $method,
        private ?string $bodyHash,
    ) {
    }

    public static function fromBodyHash(string $url, string $method, ?string $bodyHash = null): self
    {
        return new self($url, $method, $bodyHash);
    }

    public static function fromBody(string $url, string $method, string $body): self
    {
        return new self($url, $method, '' === $body ? null : hash('sha256', $body));
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getBodyHash(): ?string
    {
        return $this->bodyHash;
    }
}
