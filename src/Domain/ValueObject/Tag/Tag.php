<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use InvalidArgumentException;

final readonly class Tag
{
    /** @var list<string> */
    private array $values;

    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        private TagType $type,
        array $values,
    ) {
        $strings = array_values(array_filter($values, is_string(...)));

        if (count($strings) !== count($values)) {
            throw new InvalidArgumentException('All tag values must be strings');
        }

        $this->values = $strings;
    }

    public function getType(): TagType
    {
        return $this->type;
    }

    public function getValue(int $index = 0): ?string
    {
        return $this->values[$index] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function hasValue(string $value): bool
    {
        return in_array($value, $this->values, true);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return [(string) $this->type, ...$this->values];
    }

    public function equals(self $other): bool
    {
        return $this->type->equals($other->type) && $this->values === $other->values;
    }

    public static function event(string $eventId, ?string $relayUrl = null, ?string $marker = null): self
    {
        $values = [$eventId];

        if (null !== $marker) {
            $values[] = $relayUrl ?? '';
            $values[] = $marker;
        } elseif (null !== $relayUrl) {
            $values[] = $relayUrl;
        }

        return new self(TagType::event(), $values);
    }

    public static function pubkey(string $pubkey, ?string $relayUrl = null, ?string $petname = null): self
    {
        $values = [$pubkey];
        if (null !== $relayUrl) {
            $values[] = $relayUrl;
        }
        if (null !== $petname) {
            $values[] = $petname;
        }

        return new self(TagType::pubkey(), $values);
    }

    public static function hashtag(string $hashtag): self
    {
        return new self(TagType::hashtag(), [$hashtag]);
    }

    public static function identifier(string $identifier): self
    {
        return new self(TagType::identifier(), [$identifier]);
    }

    public static function create(string $type, string ...$values): self
    {
        return new self(TagType::fromString($type), $values);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $strings = array_values(array_filter($data, is_string(...)));

        if ([] === $strings || count($strings) !== count($data)) {
            return null;
        }

        if (!array_all($strings, static fn (string $value): bool => mb_check_encoding($value, 'UTF-8'))) {
            return null;
        }

        $name = array_shift($strings);
        if ('' === $name) {
            return null;
        }

        return new self(TagType::fromString($name), $strings);
    }
}
