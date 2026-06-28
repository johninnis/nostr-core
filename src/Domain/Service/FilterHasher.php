<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use stdClass;

final class FilterHasher
{
    private function __construct()
    {
    }

    public static function hash(Filter ...$filters): string
    {
        $canonical = array_map(
            static fn (Filter $filter): stdClass => self::canonicaliseFilter($filter->toArray()),
            $filters,
        );

        return hash('sha256', self::encode(self::sortByEncoding($canonical)));
    }

    /**
     * @param array<string, mixed> $filter
     */
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

        return self::sortByEncoding($items);
    }

    /**
     * @param array<mixed> $items
     *
     * @return list<mixed>
     */
    private static function sortByEncoding(array $items): array
    {
        $decorated = array_map(
            static fn (mixed $item): array => ['item' => $item, 'key' => self::encode($item)],
            array_values($items),
        );
        usort($decorated, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        return array_map(static fn (array $pair): mixed => $pair['item'], $decorated);
    }

    private static function encode(mixed $value): string
    {
        return JsonWireFormat::encode($value, JsonWireFormat::FILTER_HASH);
    }
}
