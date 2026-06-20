<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

/**
 * @extends TypedCollection<Tag>
 */
final class TagCollection extends TypedCollection
{
    private ?array $tagIndex = null;

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

    public function findByType(TagType $type): array
    {
        return $this->findByName((string) $type);
    }

    public function findByName(string $name): array
    {
        return $this->getTagIndex()[$name] ?? [];
    }

    public function hasType(TagType $type): bool
    {
        return !empty($this->findByType($type));
    }

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

    public function getPubkeys(): array
    {
        return $this->getValuesByType(TagType::pubkey());
    }

    public function getEventIds(): array
    {
        return $this->getValuesByType(TagType::event());
    }

    public function getFirstPubkeyByType(TagType $type): ?PublicKey
    {
        foreach ($this->getValuesByType($type) as $value) {
            if (PublicKey::HEX_LENGTH === strlen($value)) {
                $pubkey = PublicKey::fromHex($value);
                if (null !== $pubkey) {
                    return $pubkey;
                }
            }
        }

        return null;
    }

    private function getTagIndex(): array
    {
        if (null === $this->tagIndex) {
            $this->tagIndex = [];
            foreach ($this->items as $tag) {
                $this->tagIndex[(string) $tag->getType()][] = $tag;
            }
        }

        return $this->tagIndex;
    }

    public function toArray(): array
    {
        return array_map(static fn (Tag $tag) => $tag->toArray(), $this->items);
    }

    public function equals(self $other): bool
    {
        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this->items as $index => $tag) {
            if (!isset($other->items[$index]) || !$tag->equals($other->items[$index])) {
                return false;
            }
        }

        return true;
    }

    public static function fromArray(array $data): self
    {
        $tags = array_map(static fn (array $tagData) => Tag::fromArray($tagData), $data);

        return new self($tags);
    }

    public static function empty(): self
    {
        return new self([]);
    }
}
