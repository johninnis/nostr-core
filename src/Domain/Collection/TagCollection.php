<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

/**
 * @extends TypedCollection<Tag>
 */
final class TagCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Tag::class;
    }

    public function add(Tag $newTag): self
    {
        $type = $newTag->getType();
        $filtered = array_filter(
            $this->items,
            static fn (Tag $tag) => !$tag->getType()->equals($type) || $tag->getValue() !== $newTag->getValue()
        );

        return new self([...array_values($filtered), $newTag]);
    }

    public function remove(Tag $tagToRemove): self
    {
        $type = $tagToRemove->getType();
        $value = $tagToRemove->getValue();

        return new self(array_values(array_filter(
            $this->items,
            static fn (Tag $tag) => !$tag->getType()->equals($type) || $tag->getValue() !== $value
        )));
    }

    public function removeAll(TagType $type): self
    {
        return new self(array_values(array_filter(
            $this->items,
            static fn (Tag $tag) => !$tag->getType()->equals($type)
        )));
    }

    /**
     * @return list<Tag>
     */
    public function findByType(TagType $type): array
    {
        return $this->findByName((string) $type);
    }

    /**
     * @return list<Tag>
     */
    public function findByName(string $name): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Tag $tag): bool => (string) $tag->getType() === $name,
        ));
    }

    public function hasType(TagType $type): bool
    {
        return [] !== $this->findByType($type);
    }

    /**
     * @return list<string>
     */
    public function getValuesByType(TagType $type, int $valueIndex = 0): array
    {
        $tags = $this->findByType($type);

        return array_values(array_unique(
            array_filter(
                array_map(static fn (Tag $tag) => $tag->getValue($valueIndex), $tags),
                static fn ($value) => null !== $value
            )
        ));
    }

    public function getPubkeys(): PublicKeyCollection
    {
        return PublicKeyCollection::fromHexValues($this->getValuesByType(TagType::pubkey()));
    }

    public function getEventIds(): EventIdCollection
    {
        return EventIdCollection::fromHexValues($this->getValuesByType(TagType::event()));
    }

    public function getFirstValueByType(TagType $type): ?string
    {
        return $this->getValuesByType($type)[0] ?? null;
    }

    public function getFirstPubkeyByType(TagType $type): ?PublicKey
    {
        foreach ($this->getValuesByType($type) as $value) {
            $pubkey = PublicKey::fromHex($value);
            if (null !== $pubkey) {
                return $pubkey;
            }
        }

        return null;
    }

    /**
     * @return list<list<string>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (Tag $tag): array => $tag->toArray());
    }

    public function equals(self $other): bool
    {
        if ($this->count() !== $other->count()) {
            return false;
        }

        return array_all(
            $this->items,
            static fn (Tag $tag, int $index): bool => isset($other->items[$index]) && $tag->equals($other->items[$index]),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $tags = self::parseArraysStrict($data, Tag::fromArray(...));

        return null === $tags ? null : new self($tags);
    }
}
