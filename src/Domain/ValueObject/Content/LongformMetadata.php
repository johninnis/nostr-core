<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class LongformMetadata
{
    /**
     * @param list<string> $topics
     */
    public function __construct(
        private string $identifier,
        private ?string $title,
        private ?string $summary,
        private ?string $image,
        private ?Timestamp $publishedAt,
        private array $topics,
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

    /**
     * @return list<string>
     */
    public function getTopics(): array
    {
        return $this->topics;
    }

    public static function fromTagCollection(TagCollection $tags): ?self
    {
        $identifier = $tags->getFirstValueByType(TagType::identifier());
        if (null === $identifier) {
            return null;
        }

        $publishedAtValue = $tags->getFirstValueByType(TagType::fromString(TagType::PUBLISHED_AT));
        $publishedAt = null !== $publishedAtValue ? Timestamp::tryFromInt((int) $publishedAtValue) : null;

        return new self(
            $identifier,
            $tags->getFirstValueByType(TagType::fromString(TagType::TITLE)),
            $tags->getFirstValueByType(TagType::fromString(TagType::SUMMARY)),
            $tags->getFirstValueByType(TagType::fromString(TagType::IMAGE)),
            $publishedAt,
            $tags->getValuesByType(TagType::hashtag()),
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

        $tags = [...$tags, ...array_map(Tag::hashtag(...), $this->topics)];

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
            'topics' => $this->topics,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $identifier = JsonWireFormat::stringField($data, 'identifier');
        if (null === $identifier) {
            return null;
        }

        $publishedAtValue = JsonWireFormat::intField($data, 'published_at');
        $publishedAt = null !== $publishedAtValue ? Timestamp::tryFromInt($publishedAtValue) : null;

        $rawTopics = $data['topics'] ?? null;
        $topics = is_array($rawTopics) ? array_values(array_filter($rawTopics, is_string(...))) : [];

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
            && (
                (null === $this->publishedAt && null === $other->publishedAt)
                || (null !== $this->publishedAt && null !== $other->publishedAt && $this->publishedAt->equals($other->publishedAt))
            )
            && $this->topics === $other->topics;
    }
}
