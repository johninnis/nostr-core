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
     * @param self<T> $other
     */
    final public function merge(self $other): static
    {
        /** @var static<T> $collection */
        $collection = new static([...$this->items, ...$other->items]);

        return $collection;
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
     * The index is memoised per instance but keyed on nothing, so it is a pure function of the
     * elements ONLY while a collection uses one key function. A leaf that called this with two
     * different `$keyOf` would silently answer the second from an index built for the first — wrong
     * results, not a crash. Every leaf therefore passes its own single `self::keyOf(...)`, and a leaf
     * needing membership over a second key must not reuse this helper. The callables cannot be
     * compared to detect the mistake: `self::keyOf(...)` mints a fresh Closure per call, so an
     * identity check would rebuild the index on every call and remove the memo entirely.
     *
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
     * @param callable(mixed): (T|null) $tryParse
     *
     * @return static
     */
    final protected static function fromEach(mixed $values, callable $tryParse): self
    {
        $items = [];

        if (is_iterable($values)) {
            foreach ($values as $value) {
                $parsed = $tryParse($value);

                if (null !== $parsed) {
                    $items[] = $parsed;
                }
            }
        }

        /** @var static<T> $collection */
        $collection = new static($items);

        return $collection;
    }

    /**
     * @param callable(mixed): (T|null) $tryParse
     *
     * @return static|null
     */
    final protected static function tryFromEach(mixed $values, callable $tryParse): ?self
    {
        if (!is_iterable($values)) {
            return null;
        }

        $items = [];

        foreach ($values as $value) {
            $parsed = $tryParse($value);

            if (null === $parsed) {
                return null;
            }

            $items[] = $parsed;
        }

        /** @var static<T> $collection */
        $collection = new static($items);

        return $collection;
    }
}
