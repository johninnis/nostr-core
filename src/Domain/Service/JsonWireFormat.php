<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class JsonWireFormat
{
    // Deliberate: emits U+2028/U+2029 verbatim so event ids are reproducible; do not drop a flag to align with FILTER_HASH — see ADR-0020
    public const int EVENT = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    public const int MESSAGE = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    // Deliberate: omits JSON_UNESCAPED_UNICODE so the canonical form is pure ASCII and hashes byte-for-byte with the TypeScript side — see ADR-0020
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

    /**
     * @param array<mixed> $data
     *
     * @return array<array-key, mixed>|null
     */
    public static function arrayField(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }
}
