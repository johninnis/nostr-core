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
     * @param callable(T): string $keyOf
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
}
