<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class Base64Codec
{
    private function __construct()
    {
    }

    // Deliberate: base64_decode skips whitespace and tolerates missing padding and non-zero trailing bits even in strict mode, so only the encoding that re-encodes to itself is read — see nostr-adrs ADR-0095
    public static function tryDecodeCanonical(string $encoded): ?string
    {
        $decoded = base64_decode($encoded, true);

        return false !== $decoded && base64_encode($decoded) === $encoded ? $decoded : null;
    }

    public static function encodeUnpaddedUrl(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    // Deliberate: read through the standard decoder, then kept only when it re-encodes to itself, as tryDecodeCanonical is — see nostr-adrs ADR-0111
    public static function tryDecodeCanonicalUnpaddedUrl(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return false !== $decoded && self::encodeUnpaddedUrl($decoded) === $encoded ? $decoded : null;
    }
}
