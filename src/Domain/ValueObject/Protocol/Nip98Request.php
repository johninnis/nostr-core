<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class Nip98Request
{
    private function __construct(
        private HttpUrl $url,
        private string $method,
        private ?Sha256Hash $bodyHash,
    ) {
    }

    // Deliberate: the empty body's hash is no body, so it neither demands nor admits a payload tag — see nostr-adrs ADR-0024
    public static function fromBodyHash(HttpUrl $url, string $method, ?Sha256Hash $bodyHash = null): self
    {
        return new self($url, $method, true === $bodyHash?->isOfEmptyContent() ? null : $bodyHash);
    }

    public static function fromBody(HttpUrl $url, string $method, string $body): self
    {
        return self::fromBodyHash($url, $method, Sha256Hash::ofContent($body));
    }

    public function getUrl(): HttpUrl
    {
        return $this->url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getBodyHash(): ?Sha256Hash
    {
        return $this->bodyHash;
    }
}
