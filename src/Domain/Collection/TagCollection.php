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

    /**
     * @return list<string>
     */
    public function getPubkeys(): array
    {
        return $this->getValuesByType(TagType::pubkey());
    }

    /**
     * @return list<string>
     */
    public function getEventIds(): array
    {
        return $this->getValuesByType(TagType::event());
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

    public function toJsonArray(): array
    {
        return array_map(static fn (Tag $tag) => $tag->toArray(), $this->items);
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

    public static function fromArray(array $data): ?self
    {
        $tags = [];
        foreach ($data as $tagData) {
            if (!is_array($tagData)) {
                return null;
            }

            $tag = Tag::fromArray($tagData);
            if (null === $tag) {
                return null;
            }

            $tags[] = $tag;
        }

        return new self($tags);
    }

    public static function empty(): self
    {
        return new self();
    }
}
