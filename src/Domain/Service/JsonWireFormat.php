<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class JsonWireFormat
{
    // NIP-01 ids require U+2028/U+2029 emitted verbatim, which PHP escapes even under
    // JSON_UNESCAPED_UNICODE; without JSON_UNESCAPED_LINE_TERMINATORS ids are irreproducible.
    public const int EVENT = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    public const int MESSAGE = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    // No JSON_UNESCAPED_UNICODE: non-ASCII is escaped as lowercase \uXXXX (astral chars as surrogate
    // pairs), so the canonical form is pure ASCII and bytewise sorting agrees with the TypeScript
    // hashFilters implementation byte-for-byte.
    public const int FILTER_HASH = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    private function __construct()
    {
    }

    /**
     * @param positive-int $depth
     *
     * @return array<mixed>|null
     */
    public static function decodeArray(string $json, int $depth = 512): ?array
    {
        if (!json_validate($json, $depth)) {
            return null;
        }

        $decoded = json_decode($json, true, $depth);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function stringField(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function intField(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
