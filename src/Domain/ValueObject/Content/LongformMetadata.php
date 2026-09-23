<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class LongformMetadata
{
    public function __construct(
        private string $identifier,
        private ?string $title,
        private ?string $summary,
        private ?string $image,
        private ?Timestamp $publishedAt,
        private HashtagCollection $topics,
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getPublishedAt(): ?Timestamp
    {
        return $this->publishedAt;
    }

    public function getTopics(): HashtagCollection
    {
        return $this->topics;
    }

    public static function tryFromTagCollection(TagCollection $tags): ?self
    {
        $identifier = $tags->getFirstValueByType(TagType::identifier());
        if (null === $identifier) {
            return null;
        }

        $publishedAtValue = $tags->getFirstValueByType(TagType::fromString(TagType::PUBLISHED_AT));
        $publishedAt = null !== $publishedAtValue ? Timestamp::tryFromDecimalString($publishedAtValue) : null;

        return new self(
            $identifier,
            $tags->getFirstValueByType(TagType::fromString(TagType::TITLE)),
            $tags->getFirstValueByType(TagType::fromString(TagType::SUMMARY)),
            $tags->getFirstValueByType(TagType::fromString(TagType::IMAGE)),
            $publishedAt,
            $tags->getHashtags(),
        );
    }

    public function toTags(): TagCollection
    {
        $tags = [Tag::identifier($this->identifier)];

        if (null !== $this->title) {
            $tags[] = Tag::create(TagType::TITLE, $this->title);
        }

        if (null !== $this->summary) {
            $tags[] = Tag::create(TagType::SUMMARY, $this->summary);
        }

        if (null !== $this->image) {
            $tags[] = Tag::create(TagType::IMAGE, $this->image);
        }

        if (null !== $this->publishedAt) {
            $tags[] = Tag::create(TagType::PUBLISHED_AT, (string) $this->publishedAt->toInt());
        }

        $tags = [...$tags, ...array_map(Tag::hashtag(...), $this->topics->toArray())];

        return new TagCollection($tags);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'title' => $this->title,
            'summary' => $this->summary,
            'image' => $this->image,
            'published_at' => $this->publishedAt?->toInt(),
            'topics' => $this->topics->toStrings(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function tryFromArray(array $data): ?self
    {
        $identifier = JsonWireFormat::stringField($data, 'identifier');
        if (null === $identifier) {
            return null;
        }

        $publishedAtValue = JsonWireFormat::intField($data, 'published_at');
        $publishedAt = null !== $publishedAtValue ? Timestamp::tryFromInt($publishedAtValue) : null;

        $topics = HashtagCollection::fromStrings($data['topics'] ?? null);

        return new self(
            $identifier,
            JsonWireFormat::stringField($data, 'title'),
            JsonWireFormat::stringField($data, 'summary'),
            JsonWireFormat::stringField($data, 'image'),
            $publishedAt,
            $topics,
        );
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier
            && $this->title === $other->title
            && $this->summary === $other->summary
            && $this->image === $other->image
            && $this->publishedAt?->toInt() === $other->publishedAt?->toInt()
            && $this->topics->equals($other->topics);
    }
}
