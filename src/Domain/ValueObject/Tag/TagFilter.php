<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class TagFilter
{
    private const string TAG_NAME_PATTERN = '/^[a-zA-Z]$/D';

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
     * @param array<array-key, mixed> $values
     */
    public static function tryFromValues(array $values): ?self
    {
        $parsed = [];

        foreach ($values as $tagName => $tagValues) {
            $name = (string) $tagName;

            if (1 !== preg_match(self::TAG_NAME_PATTERN, $name) || !is_array($tagValues) || !array_is_list($tagValues)) {
                return null;
            }

            $strings = array_values(array_filter($tagValues, is_string(...)));

            if (count($strings) !== count($tagValues)
                || !array_all($strings, static fn (string $value): bool => self::isValueFor($name, $value))
            ) {
                return null;
            }

            $parsed[$name] = $strings;
        }

        return new self($parsed, array_map(static fn (array $tagValues): array => array_flip($tagValues), $parsed));
    }

    /**
     * @param array<string, list<string>> $values
     */
    public static function fromValues(array $values): self
    {
        return self::tryFromValues($values)
            ?? throw new InvalidArgumentException('A tag filter names each tag by one letter, a-z or A-Z, and holds UTF-8 values for it, each #e and #p value 64-character lowercase hex');
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $values = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, '#')) {
                $values[substr($key, 1)] = $value;
            }
        }

        return self::tryFromValues($values);
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }

    public function canMatch(): bool
    {
        return array_all($this->values, static fn (array $tagValues): bool => [] !== $tagValues);
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

    private static function isValueFor(string $tagName, string $value): bool
    {
        return match ($tagName) {
            TagType::EVENT => null !== EventId::tryFromHex($value),
            TagType::PUBKEY => null !== PublicKey::tryFromHex($value),
            default => mb_check_encoding($value, 'UTF-8'),
        };
    }

    /**
     * @param array<string, int> $valueSet
     */
    private static function eventHasTagValue(TagCollection $eventTags, string $tagName, array $valueSet): bool
    {
        foreach ($eventTags->findByType(TagType::fromString($tagName)) as $eventTag) {
            $value = $eventTag->getValue();

            if (null !== $value && isset($valueSet[$value])) {
                return true;
            }
        }

        return false;
    }
}
