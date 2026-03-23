<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

final readonly class Tag
{
    public function __construct(
        private TagType $type,
        private array $values
    ) {
        // Tags can be empty (flag-style tags like ["content-warning"])
        // So we don't validate that values must exist

        foreach ($this->values as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('All tag values must be strings');
            }
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
        return \in_array($value, $this->values, true);
    }

    public function toArray(): array
    {
        return array_merge([(string) $this->type], $this->values);
    }

    public function equals(Tag $other): bool
    {
        return $this->type->equals($other->type) && $this->values === $other->values;
    }

    public static function event(string $eventId, ?string $relayUrl = null, ?string $marker = null): self
    {
        $values = [$eventId];

        // Per NIP-10: if marker is provided, relay URL must be at position 2 (even if empty)
        if ($marker !== null) {
            $values[] = $relayUrl ?? '';
            $values[] = $marker;
        } elseif ($relayUrl !== null) {
            $values[] = $relayUrl;
        }

        return new self(TagType::event(), $values);
    }

    public static function pubkey(string $pubkey, ?string $relayUrl = null, ?string $petname = null): self
    {
        $values = [$pubkey];
        if ($relayUrl !== null) {
            $values[] = $relayUrl;
        }
        if ($petname !== null) {
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
            throw new \InvalidArgumentException('Tag array cannot be empty');
        }

        $type = TagType::fromString((string) array_shift($data));

        return new self($type, $data);
    }
}
