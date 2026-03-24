<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use ArrayIterator;
use Countable;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;
use IteratorAggregate;

final class TagCollection implements IteratorAggregate, Countable
{
    private array $tags = [];
    private ?array $tagIndex = null;

    public function __construct(array $tags = [])
    {
        foreach ($tags as $tag) {
            if (!$tag instanceof Tag) {
                throw new InvalidArgumentException('All items must be Tag instances');
            }
            $this->tags[] = $tag;
        }
    }

    public function add(Tag $tag): self
    {
        return new self([...$this->tags, $tag]);
    }

    public function remove(TagType $type): self
    {
        return new self(array_values(array_filter(
            $this->tags,
            static fn (Tag $tag) => !$tag->getType()->equals($type)
        )));
    }

    public function findByType(TagType $type): array
    {
        return $this->getTagIndex()[(string) $type] ?? [];
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
            foreach ($this->tags as $tag) {
                $this->tagIndex[(string) $tag->getType()][] = $tag;
            }
        }

        return $this->tagIndex;
    }

    public function toArray(): array
    {
        return array_map(static fn (Tag $tag) => $tag->toArray(), $this->tags);
    }

    public function isEmpty(): bool
    {
        return empty($this->tags);
    }

    public function count(): int
    {
        return count($this->tags);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tags);
    }

    public function equals(self $other): bool
    {
        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this->tags as $index => $tag) {
            if (!isset($other->tags[$index]) || !$tag->equals($other->tags[$index])) {
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
