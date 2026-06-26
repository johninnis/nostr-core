<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Override;
use Stringable;

final readonly class RelayUrl implements Stringable
{
    private function __construct(
        private string $url,
        private string $host,
        private ?int $port,
        private string $path,
    ) {
    }

    public function isSecure(): bool
    {
        return str_starts_with($this->url, 'wss://');
    }

    public function toHttpUrl(): string
    {
        return $this->isSecure()
            ? 'https'.substr($this->url, strlen('wss'))
            : 'http'.substr($this->url, strlen('ws'));
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url;
    }

    // Deliberate: rejects ambiguous-but-well-formed URLs it cannot canonicalise, keeping equals()/unique() sound — see ADR-0010
    public static function fromString(?string $url): ?self
    {
        if (null === $url) {
            return null;
        }

        $trimmed = trim($url);
        if ('' === $trimmed) {
            return null;
        }

        $parsed = parse_url($trimmed);
        if (false === $parsed || isset($parsed['fragment']) || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['ws', 'wss'], true)) {
            return null;
        }

        $host = strtolower($parsed['host']);
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $host)) {
            return null;
        }

        $port = $parsed['port'] ?? null;
        if (null !== $port && ($port < 1 || $port > 65535)) {
            return null;
        }

        $rawPath = $parsed['path'] ?? '';
        if (str_contains($rawPath, '%20') || str_contains($rawPath, '//') || str_contains($rawPath, $host)) {
            return null;
        }

        $cleanPath = rtrim(rtrim($rawPath, ',.;!'), '/');
        $hasPath = '' !== $cleanPath && '/' !== $cleanPath;
        $canonicalPort = null !== $port && !self::isDefaultPort($scheme, $port) ? $port : null;

        $normalised = $scheme.'://'.$host;
        if (null !== $canonicalPort) {
            $normalised .= ':'.$canonicalPort;
        }
        if ($hasPath) {
            $normalised .= $cleanPath;
        }
        if (isset($parsed['query'])) {
            $normalised .= '?'.$parsed['query'];
        }

        if (strlen($normalised) > 200) {
            return null;
        }

        return new self($normalised, $host, $canonicalPort, $hasPath ? $cleanPath : '/');
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ('wss' === $scheme && 443 === $port) || ('ws' === $scheme && 80 === $port);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->url;
    }
}
