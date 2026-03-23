<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class RelayUrl
{
    private function __construct(
        private string $url,
        private string $host,
        private ?int $port,
        private string $path
    ) {
    }

    public function isSecure(): bool
    {
        return str_starts_with($this->url, 'wss://');
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

    public function equals(RelayUrl $other): bool
    {
        return $this->url === $other->url;
    }

    public static function fromString(?string $url): ?self
    {
        if ($url === null) {
            return null;
        }

        $normalised = self::normalise($url);
        if ($normalised === null) {
            return null;
        }

        if (!self::isValidUrl($normalised)) {
            return null;
        }

        $parsed = parse_url($normalised);

        return new self(
            $normalised,
            $parsed['host'] ?? '',
            $parsed['port'] ?? null,
            $parsed['path'] ?? '/'
        );
    }

    private static function normalise(string $url): ?string
    {
        $trimmed = trim($url);

        if (empty($trimmed)) {
            return null;
        }

        $lowerUrl = strtolower($trimmed);
        if (!str_starts_with($lowerUrl, 'ws://') && !str_starts_with($lowerUrl, 'wss://')) {
            return null;
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        if (isset($parsed['fragment'])) {
            return null;
        }

        $normalised = strtolower($parsed['scheme']) . '://' . strtolower($parsed['host']);

        if (isset($parsed['port'])) {
            $normalised .= ':' . $parsed['port'];
        }

        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $cleanPath = rtrim($parsed['path'], ',.;!');
            $cleanPath = rtrim($cleanPath, '/');
            if (!empty($cleanPath) && $cleanPath !== '/') {
                $normalised .= $cleanPath;
            }
        }

        if (isset($parsed['query'])) {
            $normalised .= '?' . $parsed['query'];
        }

        return $normalised;
    }

    private static function isValidUrl(string $url): bool
    {
        if (!str_starts_with($url, 'ws://') && !str_starts_with($url, 'wss://')) {
            return false;
        }

        if (\strlen($url) > 200) {
            return false;
        }

        $parsed = parse_url($url);

        if ($parsed === false
            || !isset($parsed['scheme'], $parsed['host'])
            || !\in_array($parsed['scheme'], ['ws', 'wss'], true)) {
            return false;
        }

        $afterHost = substr($url, strpos($url, $parsed['host']) + \strlen($parsed['host']));
        if (preg_match('/wss?:\/\//', $afterHost)) {
            return false;
        }

        if (isset($parsed['path']) && str_contains($parsed['path'], '%20')) {
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $parsed['host'])) {
            return false;
        }

        if (isset($parsed['port']) && ($parsed['port'] < 1 || $parsed['port'] > 65535)) {
            return false;
        }

        if (isset($parsed['path']) && str_contains($parsed['path'], '//')) {
            return false;
        }

        if (isset($parsed['path']) && str_contains($parsed['path'], $parsed['host'])) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
