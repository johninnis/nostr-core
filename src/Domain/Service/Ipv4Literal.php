<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class Ipv4Literal
{
    private const string DEC_OCTET = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)';
    private const string DOTTED_DECIMAL = '~^'.self::DEC_OCTET.'(?:\.'.self::DEC_OCTET.'){3}$~D';
    // Deliberate: the WHATWG URL parser's "ends in a number" check, which reads a host whose last label (one trailing dot dropped) is decimal or 0x-hexadecimal as an IPv4 address (127.1, 127.0x1) — see ADR-0091
    private const string NUMERIC_LAST_LABEL = '~(?:^|\.)(?:0x[0-9a-f]*|[0-9]+)\.?$~iD';

    private function __construct()
    {
    }

    public static function matches(string $text): bool
    {
        return 1 === preg_match(self::DOTTED_DECIMAL, $text);
    }

    public static function endsInNumber(string $host): bool
    {
        return 1 === preg_match(self::NUMERIC_LAST_LABEL, $host);
    }
}
