<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\SerialisationException;
use stdClass;

final class JsonWireFormat
{
    // Deliberate: emits U+2028/U+2029 verbatim and keeps the encoder's \u00XX control escapes, so event ids match the ecosystem; do not drop a flag to align with FILTER_HASH — see ADR-0123
    public const int EVENT = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    public const int MESSAGE = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    // Deliberate: omits JSON_UNESCAPED_UNICODE so the canonical form is pure ASCII and hashes byte-for-byte with the TypeScript side — see ADR-0020
    public const int FILTER_HASH = JSON_UNESCAPED_SLASHES;

    private const int MAX_DEPTH = 512;

    private function __construct()
    {
    }

    public static function encode(mixed $value, int $flags): string
    {
        $json = json_encode($value, $flags);

        if (false === $json) {
            throw new SerialisationException('Failed to serialise value to JSON: '.json_last_error_msg());
        }

        return $json;
    }

    public static function decode(string $json): mixed
    {
        return json_validate($json, self::MAX_DEPTH) ? self::keepObjectsApartFromLists(json_decode($json, false, self::MAX_DEPTH)) : null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function decodeObject(string $json): ?array
    {
        return self::objectFields(self::decode($json));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function objectFields(mixed $decoded): ?array
    {
        return match (true) {
            $decoded instanceof stdClass => get_object_vars($decoded),
            is_array($decoded) && !array_is_list($decoded) => $decoded,
            default => null,
        };
    }

    // Deliberate: a JSON object that would read back as a PHP list, {} or one keyed 0 to n-1, stays a stdClass, so every list decoded here was a JSON array — see ADR-0112
    private static function keepObjectsApartFromLists(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::keepObjectsApartFromLists(...), $value);
        }

        if (!$value instanceof stdClass) {
            return $value;
        }

        $fields = array_map(self::keepObjectsApartFromLists(...), get_object_vars($value));

        return array_is_list($fields) ? (object) $fields : $fields;
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
     */
    public static function boolField(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<array-key, mixed>|null
     */
    public static function objectField(array $data, string $key): ?array
    {
        return self::objectFields($data[$key] ?? null);
    }

    /**
     * @template T
     *
     * @param array<mixed>              $data
     * @param callable(mixed): (T|null) $parseElement
     *
     * @return list<T>|null
     */
    public static function listField(array $data, string $key, callable $parseElement): ?array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        $elements = array_values(array_filter(array_map($parseElement, $value), static fn (mixed $element): bool => null !== $element));

        return count($elements) === count($value) ? $elements : null;
    }
}
