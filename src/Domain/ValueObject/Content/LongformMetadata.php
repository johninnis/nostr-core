<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
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

        $publishedAtValue = (int) ($tags->getFirstValueByType(TagType::fromString(TagType::PUBLISHED_AT)) ?? '0');
        $publishedAt = $publishedAtValue > 0 ? Timestamp::fromInt($publishedAtValue) : null;

        return new self(
            $identifier,
            $tags->getFirstValueByType(TagType::fromString(TagType::TITLE)),
            $tags->getFirstValueByType(TagType::fromString(TagType::SUMMARY)),
            $tags->getFirstValueByType(TagType::fromString(TagType::IMAGE)),
            $publishedAt,
            array_values($tags->getValuesByType(TagType::hashtag())),
        );
    }

    public function toTags(): TagCollection
    {
        $tags = [Tag::identifier($this->identifier)];

        if (null !== $this->title) {
            $tags[] = Tag::fromArray([TagType::TITLE, $this->title]);
        }

        if (null !== $this->summary) {
            $tags[] = Tag::fromArray([TagType::SUMMARY, $this->summary]);
        }

        if (null !== $this->image) {
            $tags[] = Tag::fromArray([TagType::IMAGE, $this->image]);
        }

        if (null !== $this->publishedAt) {
            $tags[] = Tag::fromArray([TagType::PUBLISHED_AT, (string) $this->publishedAt->toInt()]);
        }

        foreach ($this->topics as $topic) {
            $tags[] = Tag::hashtag($topic);
        }

        return new TagCollection($tags);
    }

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

    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['title'] ?? null,
            $data['summary'] ?? null,
            $data['image'] ?? null,
            isset($data['published_at']) ? Timestamp::fromInt($data['published_at']) : null,
            $data['topics'] ?? []
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
