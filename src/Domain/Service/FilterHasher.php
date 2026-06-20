<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Filter;
use stdClass;

final class FilterHasher
{
    // No JSON_UNESCAPED_UNICODE: non-ASCII is escaped as lowercase \uXXXX (astral chars as surrogate
    // pairs), so the canonical form is pure ASCII and bytewise sorting agrees with the TS hashFilters.
    private const int ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public static function hash(Filter ...$filters): string
    {
        $canonical = array_map(
            static fn (Filter $filter): stdClass => self::canonicaliseFilter($filter->toArray()),
            $filters,
        );
        usort($canonical, static fn (stdClass $a, stdClass $b): int => strcmp(self::encode($a), self::encode($b)));

        return hash('sha256', self::encode($canonical));
    }

    private static function canonicaliseFilter(array $filter): stdClass
    {
        ksort($filter, SORT_STRING);

        return (object) array_map(static fn (mixed $value): mixed => self::canonicaliseValue($value), $filter);
    }

    private static function canonicaliseValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $items = array_map(static fn (mixed $element): mixed => self::canonicaliseValue($element), $value);
        usort($items, static fn (mixed $a, mixed $b): int => strcmp(self::encode($a), self::encode($b)));

        return $items;
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, self::ENCODE_FLAGS);
    }
}
