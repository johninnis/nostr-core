<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
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

    /**
     * Returns a collection with $tag at the end, replacing any tag with the same name and value.
     */
    public function add(Tag $tag): self
    {
        return new self([...$this->remove($tag)->items, $tag]);
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

    /**
     * @return list<Tag>
     */
    public function findByType(TagType $type): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Tag $tag): bool => $tag->getType()->equals($type),
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

    public function getCoordinates(): EventCoordinateCollection
    {
        return new EventCoordinateCollection(array_values(array_filter(
            array_map(
                static fn (Tag $tag): ?EventCoordinate => EventCoordinate::tryFromString($tag->getValue(0) ?? '', $tag->getValue(1)),
                $this->findByType(TagType::addressable()),
            ),
            static fn (?EventCoordinate $coordinate): bool => null !== $coordinate,
        )));
    }

    public function getHashtags(): HashtagCollection
    {
        return HashtagCollection::fromStrings($this->getValuesByType(TagType::hashtag()))->unique();
    }

    // Deliberate: tags of one type that disagree are no answer rather than whichever came first, and the one reader says whether the tag is absent, one value or disagreeing, so no caller re-reads the tags — see ADR-0092
    public function getSoleValueByType(TagType $type): SoleTagValue
    {
        return SoleTagValue::fromValues($this->getValuesByType($type));
    }

    // Deliberate: an event with no d tag has the empty identifier, and d tags that disagree name none — see ADR-0092
    public function getIdentifier(): ?string
    {
        $identifier = $this->getSoleValueByType(TagType::identifier());

        return SoleTagValueState::Absent === $identifier->getState() ? '' : $identifier->getValue();
    }

    public function getSolePubkeyByType(TagType $type): ?PublicKey
    {
        $value = $this->getSoleValueByType($type)->getValue();

        return null === $value ? null : PublicKey::tryFromHex($value);
    }

    public function getPublishedAt(): ?Timestamp
    {
        $value = $this->getSoleValueByType(TagType::fromString(TagType::PUBLISHED_AT))->getValue();

        return null === $value ? null : Timestamp::tryFromDecimalString($value);
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

    public static function tryFromArray(mixed $values): ?self
    {
        return self::tryFromEach($values, Tag::tryFromArray(...));
    }
}
