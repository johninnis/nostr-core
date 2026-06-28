<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Override;

/**
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 */
abstract class TypedCollection implements IteratorAggregate, Countable
{
    /** @var list<T> */
    protected readonly array $items;

    // Deliberate: lazily memoised membership index, permitted because the collection is not readonly — see ADR-0024
    /** @var array<array-key, true>|null */
    private ?array $membershipIndex = null;

    /**
     * @param array<array-key, mixed> $items
     */
    final public function __construct(array $items = [])
    {
        $type = $this->elementType();
        $validated = [];

        foreach ($items as $item) {
            if (!$item instanceof $type) {
                throw new InvalidArgumentException(sprintf('All items must be %s instances', $type));
            }

            $validated[] = $item;
        }

        $this->items = $validated;
    }

    /**
     * @return class-string<T>
     */
    abstract protected function elementType(): string;

    final public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    #[Override]
    final public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, T>
     */
    #[Override]
    final public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<T>
     */
    final public function toArray(): array
    {
        return $this->items;
    }

    /**
     * @template TValue
     *
     * @param callable(T): TValue $map
     *
     * @return list<TValue>
     */
    final protected function mapItems(callable $map): array
    {
        return array_map($map, $this->items);
    }

    /**
     * @param callable(T): array-key $keyOf
     *
     * @return list<T>
     */
    final protected function deduplicate(callable $keyOf): array
    {
        $unique = [];

        foreach ($this->items as $item) {
            $unique[$keyOf($item)] ??= $item;
        }

        return array_values($unique);
    }

    /**
     * @param array-key              $key
     * @param callable(T): array-key $keyOf
     */
    final protected function containsByKey(int|string $key, callable $keyOf): bool
    {
        $this->membershipIndex ??= array_fill_keys(array_map($keyOf, $this->items), true);

        return isset($this->membershipIndex[$key]);
    }

    /**
     * @param self<T>                $other
     * @param callable(T): array-key $keyOf
     *
     * @return list<T>
     */
    final protected function retainByKey(self $other, callable $keyOf, bool $present): array
    {
        $otherKeys = array_fill_keys(array_map($keyOf, $other->items), true);

        return array_values(array_filter(
            $this->items,
            static fn (object $item): bool => isset($otherKeys[$keyOf($item)]) === $present,
        ));
    }

    /**
     * @param callable(mixed): (T|null) $parse
     *
     * @return list<T>|null
     */
    final protected static function parseAll(mixed $values, callable $parse): ?array
    {
        if (!is_iterable($values)) {
            return null;
        }

        $items = [];

        foreach ($values as $value) {
            $parsed = $parse($value);

            if (null === $parsed) {
                return null;
            }

            $items[] = $parsed;
        }

        return $items;
    }

    /**
     * @param callable(string): (T|null) $parse
     *
     * @return list<T>
     */
    final protected static function parseStrings(mixed $values, callable $parse): array
    {
        $items = [];

        if (is_iterable($values)) {
            foreach ($values as $value) {
                $parsed = is_string($value) ? $parse($value) : null;

                if (null !== $parsed) {
                    $items[] = $parsed;
                }
            }
        }

        return $items;
    }

    /**
     * @param callable(int): (T|null) $parse
     *
     * @return list<T>
     */
    final protected static function parseInts(mixed $values, callable $parse): array
    {
        $items = [];

        if (is_iterable($values)) {
            foreach ($values as $value) {
                $parsed = is_int($value) ? $parse($value) : null;

                if (null !== $parsed) {
                    $items[] = $parsed;
                }
            }
        }

        return $items;
    }

    /**
     * @param callable(array<array-key, mixed>): (T|null) $parse
     *
     * @return list<T>
     */
    final protected static function parseArrays(mixed $values, callable $parse): array
    {
        $items = [];

        if (is_iterable($values)) {
            foreach ($values as $value) {
                $parsed = is_array($value) ? $parse($value) : null;

                if (null !== $parsed) {
                    $items[] = $parsed;
                }
            }
        }

        return $items;
    }
}
