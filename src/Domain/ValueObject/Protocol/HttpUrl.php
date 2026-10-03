<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\Ipv4Literal;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class HttpUrl implements Stringable
{
    private const array DEFAULT_PORTS = ['http' => '80', 'https' => '443'];
    private const string HTTP_URL = '~^([A-Za-z][A-Za-z0-9+.-]*)://([^/?#]*)([^?#]*)(\?[^#]*)?(?:#.*)?$~D';
    private const string IP_LITERAL_AUTHORITY = '~^(\[([^\]]*)\])(?::(.*))?$~D';
    private const string REG_NAME = '~^(?:[A-Za-z0-9\-._\~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})+$~D';
    private const string CANONICAL_PORT = '~^(?:[1-9]\d{0,4})?$~D';
    private const string NO_CONTROL_CHARACTER = '~^[^\x{00}-\x{1F}\x{7F}-\x{9F}]*$~uD';
    private const string H16 = '~^[0-9A-Fa-f]{1,4}$~D';
    private const int IPV6_PIECES = 8;
    private const int MAX_PORT = 65535;

    private function __construct(private string $url)
    {
    }

    public static function tryFromString(mixed $url): ?self
    {
        if (!is_string($url) || 1 !== preg_match(self::NO_CONTROL_CHARACTER, $url)
            || 1 !== preg_match(self::HTTP_URL, $url, $parts, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $scheme = strtolower($parts[1]);
        $hostAndPort = self::splitHostAndPort($parts[2]);

        if (!array_key_exists($scheme, self::DEFAULT_PORTS) || null === $hostAndPort) {
            return null;
        }

        [$host, $port] = $hostAndPort;

        if (1 !== preg_match(self::CANONICAL_PORT, $port) || (int) $port > self::MAX_PORT) {
            return null;
        }

        $authority = '' === $port || self::DEFAULT_PORTS[$scheme] === $port ? strtolower($host) : strtolower($host).':'.$port;
        $path = $parts[3];

        return new self($scheme.'://'.$authority.('' === $path ? '/' : $path).($parts[4] ?? ''));
    }

    public static function fromString(string $url): self
    {
        return self::tryFromString($url) ?? throw new InvalidArgumentException('An HTTP URL needs an http or https scheme, a host that is an RFC 3986 registered name or an IPv6 literal, a canonical port, and UTF-8 text without a control character');
    }

    /**
     * @return array{string, string}|null
     */
    private static function splitHostAndPort(string $authority): ?array
    {
        $at = strrpos($authority, '@');
        $hostAndPort = false === $at ? $authority : substr($authority, $at + 1);

        if (str_starts_with($hostAndPort, '[')) {
            return 1 === preg_match(self::IP_LITERAL_AUTHORITY, $hostAndPort, $literal, PREG_UNMATCHED_AS_NULL) && self::isIpv6Address($literal[2])
                ? [$literal[1], $literal[3] ?? '']
                : null;
        }

        $colon = strpos($hostAndPort, ':');
        $host = false === $colon ? $hostAndPort : substr($hostAndPort, 0, $colon);

        return 1 === preg_match(self::REG_NAME, $host) ? [$host, false === $colon ? '' : substr($hostAndPort, $colon + 1)] : null;
    }

    private static function isIpv6Address(string $text): bool
    {
        $halves = explode('::', $text);

        if (count($halves) > 2) {
            return false;
        }

        $groups = array_merge(...array_map(static fn (string $half): array => '' === $half ? [] : explode(':', $half), $halves));
        $colonSeparated = explode(':', $text);
        $endsInIpv4 = Ipv4Literal::matches($colonSeparated[array_key_last($colonSeparated)]);
        $pieces = $endsInIpv4 ? count($groups) + 1 : count($groups);
        $h16s = $endsInIpv4 ? array_slice($groups, 0, -1) : $groups;

        return array_all($h16s, static fn (string $group): bool => 1 === preg_match(self::H16, $group))
            && (2 === count($halves) ? $pieces < self::IPV6_PIECES : self::IPV6_PIECES === $pieces);
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->url;
    }
}
