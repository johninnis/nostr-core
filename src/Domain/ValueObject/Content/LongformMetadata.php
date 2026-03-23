<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

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
        private array $topics
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
        $identifierValues = $tags->getValuesByType(TagType::identifier());
        if (empty($identifierValues)) {
            return null;
        }

        $titleValues = $tags->getValuesByType(TagType::fromString('title'));
        $summaryValues = $tags->getValuesByType(TagType::fromString('summary'));
        $imageValues = $tags->getValuesByType(TagType::fromString('image'));
        $publishedAtValues = $tags->getValuesByType(TagType::fromString('published_at'));
        $topics = array_values($tags->getValuesByType(TagType::hashtag()));

        $publishedAt = null;
        if (!empty($publishedAtValues)) {
            $value = (int) reset($publishedAtValues);
            if ($value > 0) {
                $publishedAt = Timestamp::fromInt($value);
            }
        }

        return new self(
            reset($identifierValues),
            !empty($titleValues) ? reset($titleValues) : null,
            !empty($summaryValues) ? reset($summaryValues) : null,
            !empty($imageValues) ? reset($imageValues) : null,
            $publishedAt,
            $topics
        );
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
                ($this->publishedAt === null && $other->publishedAt === null)
                || ($this->publishedAt !== null && $other->publishedAt !== null && $this->publishedAt->equals($other->publishedAt))
            )
            && $this->topics === $other->topics;
    }
}
