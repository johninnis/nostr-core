<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\Ipv4Literal;
use Innis\Nostr\Core\Domain\ValueObject\IdentityKeyedInterface;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class RelayUrl implements Stringable, IdentityKeyedInterface
{
    private const string URL_CHARACTERS = '#^[A-Za-z0-9\-._~:/?\[\]@!$&()*+,;=%]+$#D';
    private const string ENCODED_CONTROL = '/%(?:[01][0-9a-f]|20|7f)/i';
    private const string AUTHORITY_WITH_DIGIT_PORT = '#^[^:/?\#]+://[^:/?\#]+(?::[0-9]*)?(?:[/?\#]|$)#D';

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

    public function toHttpUrl(): HttpUrl
    {
        return HttpUrl::fromString($this->isSecure()
            ? 'https'.substr($this->url, strlen('wss'))
            : 'http'.substr($this->url, strlen('ws')));
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

    #[Override]
    public function identityKey(): string
    {
        return $this->url;
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url;
    }

    // Deliberate: rejects ambiguous-but-well-formed URLs it cannot canonicalise, keeping equals()/unique() sound — see ADR-0074
    public static function tryFromString(mixed $url): ?self
    {
        if (!is_string($url)) {
            return null;
        }

        $trimmed = trim($url);
        if (1 !== preg_match(self::URL_CHARACTERS, $trimmed) || 1 === preg_match(self::ENCODED_CONTROL, $trimmed) || 1 !== preg_match(self::AUTHORITY_WITH_DIGIT_PORT, $trimmed)) {
            return null;
        }

        $parsed = parse_url($trimmed);
        if (false === $parsed || isset($parsed['user']) || isset($parsed['pass']) || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['ws', 'wss'], true)) {
            return null;
        }

        $host = strtolower($parsed['host']);
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/D', $host) || str_contains($host, '..') || !self::isCanonicalNumericHost($host)) {
            return null;
        }

        $port = $parsed['port'] ?? null;
        if (0 === $port) {
            return null;
        }

        $cleanPath = rtrim(self::removeDotSegments($parsed['path'] ?? ''), ',.;!/');
        if (str_contains($cleanPath, '//') || str_contains($cleanPath, $host)) {
            return null;
        }

        $hasPath = '' !== $cleanPath;
        $canonicalPort = null !== $port && !self::isDefaultPort($scheme, $port) ? $port : null;

        $normalised = $scheme.'://'.$host;
        if (null !== $canonicalPort) {
            $normalised .= ':'.$canonicalPort;
        }
        if ($hasPath) {
            $normalised .= $cleanPath;
        }
        if (isset($parsed['query']) && '' !== $parsed['query']) {
            $normalised .= '?'.$parsed['query'];
        }

        if (strlen($normalised) > 200) {
            return null;
        }

        if (1 === preg_match('#wss?://#', substr($normalised, strlen($scheme.'://'.$host)))) {
            return null;
        }

        return new self($normalised, $host, $canonicalPort, $hasPath ? $cleanPath : '/');
    }

    public static function fromString(string $url): self
    {
        return self::tryFromString($url) ?? throw new InvalidArgumentException('A relay URL needs a ws or wss scheme and a host, and reads only unambiguously: no credentials, fragment or port 0, and a canonical form of at most 200 characters');
    }

    private static function isCanonicalNumericHost(string $host): bool
    {
        return !Ipv4Literal::endsInNumber($host) || Ipv4Literal::matches($host);
    }

    private static function removeDotSegments(string $path): string
    {
        $output = [];
        foreach (explode('/', $path) as $segment) {
            $dots = str_ireplace('%2e', '.', $segment);
            if ('..' === $dots) {
                array_pop($output);
            } elseif ('.' !== $dots) {
                $output[] = $segment;
            }
        }

        $resolved = implode('/', $output);

        return str_starts_with($resolved, '/') || '' === $resolved ? $resolved : '/'.$resolved;
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
