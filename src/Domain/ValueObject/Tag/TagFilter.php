<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use InvalidArgumentException;

final readonly class TagFilter
{
    /**
     * @param array<string, list<string>>       $values
     * @param array<string, array<string, int>> $index
     */
    private function __construct(
        private array $values,
        private array $index,
    ) {
    }

    /**
     * @param array<string, list<string>> $values
     */
    public static function fromValues(array $values): self
    {
        foreach ($values as $tagName => $tagValues) {
            if (count($tagValues) > Filter::MAX_VALUES_PER_FIELD) {
                throw new InvalidArgumentException(sprintf('Tag filter "#%s" may contain at most %d values', $tagName, Filter::MAX_VALUES_PER_FIELD));
            }
        }

        return new self($values, self::indexOf($values));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromWire(array $data): ?self
    {
        $values = [];

        foreach ($data as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, '#')) {
                continue;
            }

            $tagName = substr($key, 1);

            if ('' === $tagName || !is_array($value)) {
                return null;
            }

            $tagValues = array_values(array_filter($value, is_string(...)));

            if (!mb_check_encoding($tagName, 'UTF-8')
                || !array_all($tagValues, static fn (string $tagValue): bool => mb_check_encoding($tagValue, 'UTF-8'))
                || count($tagValues) > Filter::MAX_VALUES_PER_FIELD
            ) {
                return null;
            }

            $values[$tagName] = $tagValues;
        }

        return new self($values, self::indexOf($values));
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }

    public function matches(TagCollection $eventTags): bool
    {
        foreach ($this->index as $tagName => $valueSet) {
            if (!self::eventHasTagValue($eventTags, (string) $tagName, $valueSet)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        $wire = [];

        foreach ($this->values as $tagName => $tagValues) {
            $wire['#'.$tagName] = $tagValues;
        }

        return $wire;
    }

    /**
     * @param array<string, list<string>> $values
     *
     * @return array<string, array<string, int>>
     */
    private static function indexOf(array $values): array
    {
        return array_map(static fn (array $tagValues): array => array_flip($tagValues), $values);
    }

    /**
     * @param array<string, int> $valueSet
     */
    private static function eventHasTagValue(TagCollection $eventTags, string $tagName, array $valueSet): bool
    {
        foreach ($eventTags->findByName($tagName) as $eventTag) {
            $value = $eventTag->getValue();

            if (null !== $value && isset($valueSet[$value])) {
                return true;
            }
        }

        return false;
    }
}
