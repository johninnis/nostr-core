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
        return $this->withItems([...$this->items, ...$other->items]);
    }

    /**
     * @param list<T> $items
     */
    final protected function withItems(array $items): static
    {
        /** @var static<T> $collection */
        $collection = new static($items);

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
        if (!is_array($values) || !array_is_list($values)) {
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
