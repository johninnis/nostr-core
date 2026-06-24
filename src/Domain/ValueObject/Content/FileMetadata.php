<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class FileMetadata
{
    private const string IMETA_TYPE = 'imeta';

    /**
     * @param list<string> $fallbacks
     */
    public function __construct(
        private string $url,
        private ?string $mimeType = null,
        private ?string $hash = null,
        private ?string $originalHash = null,
        private ?int $size = null,
        private ?string $dimensions = null,
        private ?string $blurhash = null,
        private ?string $thumbnail = null,
        private ?string $image = null,
        private ?string $summary = null,
        private ?string $alt = null,
        private array $fallbacks = [],
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function getOriginalHash(): ?string
    {
        return $this->originalHash;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getDimensions(): ?string
    {
        return $this->dimensions;
    }

    public function getBlurhash(): ?string
    {
        return $this->blurhash;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    /**
     * @return list<string>
     */
    public function getFallbacks(): array
    {
        return $this->fallbacks;
    }

    public static function fromTagCollection(TagCollection $tags): ?self
    {
        $fields = [];
        foreach ($tags as $tag) {
            $value = $tag->getValue();
            if (null !== $value) {
                $fields[(string) $tag->getType()][] = $value;
            }
        }

        return self::fromFields($fields);
    }

    public static function fromImetaTag(Tag $tag): ?self
    {
        if (!$tag->getType()->is(self::IMETA_TYPE)) {
            return null;
        }

        $fields = [];
        foreach ($tag->getValues() as $entry) {
            $boundary = strpos($entry, ' ');
            if (false === $boundary) {
                continue;
            }

            $fields[substr($entry, 0, $boundary)][] = substr($entry, $boundary + 1);
        }

        return self::fromFields($fields);
    }

    public function toTags(): TagCollection
    {
        return new TagCollection(array_map(
            static fn (array $field): Tag => new Tag(TagType::fromString((string) $field[0]), array_slice($field, 1)),
            $this->fields(),
        ));
    }

    public function toImetaTag(): Tag
    {
        $entries = array_map(
            static fn (array $field): string => $field[0].' '.$field[1],
            $this->fields(),
        );

        return new Tag(TagType::fromString(self::IMETA_TYPE), $entries);
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url
            && $this->mimeType === $other->mimeType
            && $this->hash === $other->hash
            && $this->originalHash === $other->originalHash
            && $this->size === $other->size
            && $this->dimensions === $other->dimensions
            && $this->blurhash === $other->blurhash
            && $this->thumbnail === $other->thumbnail
            && $this->image === $other->image
            && $this->summary === $other->summary
            && $this->alt === $other->alt
            && $this->fallbacks === $other->fallbacks;
    }

    private function fields(): array
    {
        $fields = [['url', $this->url]];

        if (null !== $this->mimeType) {
            $fields[] = ['m', $this->mimeType];
        }
        if (null !== $this->hash) {
            $fields[] = ['x', $this->hash];
        }
        if (null !== $this->originalHash) {
            $fields[] = ['ox', $this->originalHash];
        }
        if (null !== $this->size) {
            $fields[] = ['size', (string) $this->size];
        }
        if (null !== $this->dimensions) {
            $fields[] = ['dim', $this->dimensions];
        }
        if (null !== $this->blurhash) {
            $fields[] = ['blurhash', $this->blurhash];
        }
        if (null !== $this->thumbnail) {
            $fields[] = ['thumb', $this->thumbnail];
        }
        if (null !== $this->image) {
            $fields[] = ['image', $this->image];
        }
        if (null !== $this->summary) {
            $fields[] = ['summary', $this->summary];
        }
        if (null !== $this->alt) {
            $fields[] = ['alt', $this->alt];
        }
        foreach ($this->fallbacks as $fallback) {
            $fields[] = ['fallback', $fallback];
        }

        return $fields;
    }

    private static function fromFields(array $fields): ?self
    {
        $url = self::firstString($fields, 'url');
        if (null === $url) {
            return null;
        }

        $size = self::firstString($fields, 'size');

        return new self(
            $url,
            self::firstString($fields, 'm'),
            self::firstString($fields, 'x'),
            self::firstString($fields, 'ox'),
            null !== $size && is_numeric($size) ? (int) $size : null,
            self::firstString($fields, 'dim'),
            self::firstString($fields, 'blurhash'),
            self::firstString($fields, 'thumb'),
            self::firstString($fields, 'image'),
            self::firstString($fields, 'summary'),
            self::firstString($fields, 'alt'),
            self::stringList($fields, 'fallback'),
        );
    }

    private static function firstString(array $fields, string $key): ?string
    {
        $values = $fields[$key] ?? null;
        if (!is_array($values)) {
            return null;
        }

        $value = $values[0] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(array $fields, string $key): array
    {
        $values = $fields[$key] ?? null;
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, is_string(...)));
    }
}
