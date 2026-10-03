<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\DecimalIntegerParser;
use Innis\Nostr\Core\Domain\Service\MimeTypeParser;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;

final readonly class FileMetadata
{
    /**
     * @param list<string> $fallbacks
     */
    private function __construct(
        private string $url,
        private ?string $mimeType,
        private ?string $hash,
        private ?string $originalHash,
        private ?int $size,
        private ?string $dimensions,
        private ?string $blurhash,
        private ?string $thumbnail,
        private ?string $image,
        private ?string $summary,
        private ?string $alt,
        private array $fallbacks,
    ) {
    }

    /**
     * @param list<string> $fallbacks
     */
    public static function tryFrom(
        string $url,
        ?string $mimeType = null,
        ?string $hash = null,
        ?string $originalHash = null,
        ?int $size = null,
        ?string $dimensions = null,
        ?string $blurhash = null,
        ?string $thumbnail = null,
        ?string $image = null,
        ?string $summary = null,
        ?string $alt = null,
        array $fallbacks = [],
    ): ?self {
        $metadata = new self($url, $mimeType, $hash, $originalHash, $size, $dimensions, $blurhash, $thumbnail, $image, $summary, $alt, $fallbacks);

        return null === $metadata->refusal() ? $metadata : null;
    }

    /**
     * @param list<string> $fallbacks
     */
    public static function from(
        string $url,
        ?string $mimeType = null,
        ?string $hash = null,
        ?string $originalHash = null,
        ?int $size = null,
        ?string $dimensions = null,
        ?string $blurhash = null,
        ?string $thumbnail = null,
        ?string $image = null,
        ?string $summary = null,
        ?string $alt = null,
        array $fallbacks = [],
    ): self {
        $metadata = new self($url, $mimeType, $hash, $originalHash, $size, $dimensions, $blurhash, $thumbnail, $image, $summary, $alt, $fallbacks);
        $refusal = $metadata->refusal();

        return null === $refusal ? $metadata : throw new InvalidArgumentException($refusal);
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

    public static function tryFromTagCollection(TagCollection $tags): ?self
    {
        $fields = [];
        foreach ($tags as $tag) {
            $value = $tag->getValue();
            if (null !== $value) {
                $fields[(string) $tag->getType()][] = $value;
            }
        }

        return self::tryFromFields($fields);
    }

    public static function tryFromImetaTag(Tag $tag): ?self
    {
        if (!$tag->getType()->is(TagType::IMETA)) {
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

        $metadata = self::tryFromFields($fields);

        return true === $metadata?->describesTheUrl() ? $metadata : null;
    }

    public function toTags(): TagCollection
    {
        return new TagCollection(array_map(
            /** @param list<string> $field */
            static fn (array $field): Tag => Tag::fromArray($field),
            $this->fields(),
        ));
    }

    public function toImetaTag(): ?Tag
    {
        if (!$this->describesTheUrl()) {
            return null;
        }

        $entries = array_map(
            /** @param list<string> $field */
            static fn (array $field): string => $field[0].' '.$field[1],
            $this->fields(),
        );

        return Tag::fromArray([TagType::IMETA, ...$entries]);
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

    /**
     * @return list<list<string>>
     */
    private function fields(): array
    {
        $fields = [[TagType::FILE_URL, $this->url]];

        if (null !== $this->mimeType) {
            $fields[] = [TagType::MIME_TYPE, $this->mimeType];
        }
        if (null !== $this->hash) {
            $fields[] = [TagType::SHA256, $this->hash];
        }
        if (null !== $this->originalHash) {
            $fields[] = [TagType::ORIGINAL_SHA256, $this->originalHash];
        }
        if (null !== $this->size) {
            $fields[] = [TagType::SIZE, (string) $this->size];
        }
        if (null !== $this->dimensions) {
            $fields[] = [TagType::DIMENSIONS, $this->dimensions];
        }
        if (null !== $this->blurhash) {
            $fields[] = [TagType::BLURHASH, $this->blurhash];
        }
        if (null !== $this->thumbnail) {
            $fields[] = [TagType::THUMBNAIL, $this->thumbnail];
        }
        if (null !== $this->image) {
            $fields[] = [TagType::IMAGE, $this->image];
        }
        if (null !== $this->summary) {
            $fields[] = [TagType::SUMMARY, $this->summary];
        }
        if (null !== $this->alt) {
            $fields[] = [TagType::ALT, $this->alt];
        }
        foreach ($this->fallbacks as $fallback) {
            $fields[] = [TagType::FALLBACK, $fallback];
        }

        return $fields;
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private static function tryFromFields(array $fields): ?self
    {
        $size = self::soleValue($fields, TagType::SIZE);
        $mimeType = self::soleValue($fields, TagType::MIME_TYPE);

        return self::tryFrom(
            self::soleValue($fields, TagType::FILE_URL) ?? '',
            null === $mimeType ? null : MimeTypeParser::tryParse($mimeType),
            self::soleValue($fields, TagType::SHA256),
            self::soleValue($fields, TagType::ORIGINAL_SHA256),
            null === $size ? null : DecimalIntegerParser::tryParse($size),
            self::soleValue($fields, TagType::DIMENSIONS),
            self::soleValue($fields, TagType::BLURHASH),
            self::soleValue($fields, TagType::THUMBNAIL),
            self::soleValue($fields, TagType::IMAGE),
            self::soleValue($fields, TagType::SUMMARY),
            self::soleValue($fields, TagType::ALT),
            $fields[TagType::FALLBACK] ?? [],
        );
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private static function soleValue(array $fields, string $key): ?string
    {
        return SoleTagValue::fromValues($fields[$key] ?? [])->getValue();
    }

    private function refusal(): ?string
    {
        return match (true) {
            '' === $this->url => 'File metadata names the URL to download the file from, and an empty URL names none',
            !array_all($this->fields(), static fn (array $field): bool => mb_check_encoding($field[1], 'UTF-8')) => 'File metadata is UTF-8 text, and a field of it is not',
            null !== $this->mimeType && MimeTypeParser::tryParse($this->mimeType) !== $this->mimeType => sprintf('File metadata states its MIME type as a lowercase type/subtype, not "%s"', $this->mimeType),
            null !== $this->size && $this->size < 0 => sprintf('File metadata states its size as a whole number of bytes, not %d', $this->size),
            default => null,
        };
    }

    private function describesTheUrl(): bool
    {
        return count($this->fields()) > 1;
    }
}
