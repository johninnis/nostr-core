<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use InvalidArgumentException;

final readonly class Tag
{
    public function __construct(
        private TagType $type,
        private array $values,
    ) {
        if (!array_all($this->values, static fn (mixed $value): bool => is_string($value))) {
            throw new InvalidArgumentException('All tag values must be strings');
        }
    }

    public function getType(): TagType
    {
        return $this->type;
    }

    public function getValue(int $index = 0): ?string
    {
        return $this->values[$index] ?? null;
    }

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

    public static function fromArray(array $data): self
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Tag array cannot be empty');
        }

        $type = TagType::fromString((string) array_shift($data));

        return new self($type, $data);
    }
}
