<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use InvalidArgumentException;

final readonly class Tag
{
    /** @var list<string> */
    private array $values;

    public function __construct(
        private TagType $type,
        array $values,
    ) {
        if (!array_all($values, static fn (mixed $value): bool => is_string($value))) {
            throw new InvalidArgumentException('All tag values must be strings');
        }

        $this->values = array_values($values);
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

    public static function fromArray(array $data): ?self
    {
        if ([] === $data || !array_all($data, static fn (mixed $value): bool => is_string($value))) {
            return null;
        }

        $name = array_shift($data);
        if ('' === $name) {
            return null;
        }

        return new self(TagType::fromString((string) $name), $data);
    }
}
