<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;

final readonly class LongformMetadata
{
    private function __construct(
        private string $identifier,
        private ?string $title,
        private ?string $summary,
        private ?HttpUrl $image,
        private ?Timestamp $publishedAt,
        private HashtagCollection $topics,
    ) {
    }

    public static function tryFrom(
        string $identifier,
        ?string $title,
        ?string $summary,
        ?HttpUrl $image,
        ?Timestamp $publishedAt,
        HashtagCollection $topics,
    ): ?self {
        $isUtf8 = array_all(
            [$identifier, $title, $summary],
            static fn (?string $text): bool => null === $text || mb_check_encoding($text, 'UTF-8'),
        );

        return $isUtf8 ? new self($identifier, $title, $summary, $image, $publishedAt, $topics->unique()) : null;
    }

    public static function from(
        string $identifier,
        ?string $title,
        ?string $summary,
        ?HttpUrl $image,
        ?Timestamp $publishedAt,
        HashtagCollection $topics,
    ): self {
        return self::tryFrom($identifier, $title, $summary, $image, $publishedAt, $topics)
            ?? throw new InvalidArgumentException('Longform identifier, title and summary must be UTF-8');
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

    public function getImage(): ?HttpUrl
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
        $identifier = $tags->getIdentifier();
        if (null === $identifier) {
            return null;
        }

        return self::tryFrom(
            $identifier,
            $tags->getSoleValueByType(TagType::fromString(TagType::TITLE))->getValue(),
            $tags->getSoleValueByType(TagType::fromString(TagType::SUMMARY))->getValue(),
            HttpUrl::tryFromString($tags->getSoleValueByType(TagType::fromString(TagType::IMAGE))->getValue()),
            $tags->getPublishedAt(),
            $tags->getHashtags(),
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
            $tags[] = Tag::fromArray([TagType::IMAGE, (string) $this->image]);
        }

        if (null !== $this->publishedAt) {
            $tags[] = Tag::fromArray([TagType::PUBLISHED_AT, (string) $this->publishedAt->toInt()]);
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
            'image' => $this->image?->__toString(),
            'published_at' => $this->publishedAt?->toInt(),
            'topics' => $this->topics->toStrings(),
        ];
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $identifier = JsonWireFormat::stringField($data, 'identifier');
        if (null === $identifier) {
            return null;
        }

        $publishedAtValue = JsonWireFormat::intField($data, 'published_at');
        $publishedAt = null !== $publishedAtValue ? Timestamp::tryFromInt($publishedAtValue) : null;

        return self::tryFrom(
            $identifier,
            JsonWireFormat::stringField($data, 'title'),
            JsonWireFormat::stringField($data, 'summary'),
            HttpUrl::tryFromString(JsonWireFormat::stringField($data, 'image')),
            $publishedAt,
            HashtagCollection::fromStrings($data['topics'] ?? null),
        );
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier
            && $this->title === $other->title
            && $this->summary === $other->summary
            && (null === $this->image ? null === $other->image : null !== $other->image && $this->image->equals($other->image))
            && $this->publishedAt?->toInt() === $other->publishedAt?->toInt()
            && $this->topics->equals($other->topics);
    }
}
