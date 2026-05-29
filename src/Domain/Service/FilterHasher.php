<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Filter;

final class FilterHasher
{
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public static function hash(Filter ...$filters): string
    {
        $wireFilters = array_map(static fn (Filter $filter): array => $filter->toArray(), $filters);

        return hash('sha256', self::encode(self::canonicalise($wireFilters)));
    }

    private static function canonicalise(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $items = array_map(static fn (mixed $element): mixed => self::canonicalise($element), $value);
            usort($items, static fn (mixed $a, mixed $b): int => strcmp(self::encode($a), self::encode($b)));

            return $items;
        }

        ksort($value);

        return array_map(static fn (mixed $element): mixed => self::canonicalise($element), $value);
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, self::ENCODE_FLAGS);
    }
}
