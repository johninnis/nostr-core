<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class HighlightMetadata
{
    public function __construct(
        private ?string $context,
        private ?string $comment,
        private ?string $sourceUrl,
    ) {
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public static function fromTagCollection(TagCollection $tags): self
    {
        return new self(
            $tags->getFirstValueByType(TagType::fromString(TagType::CONTEXT)),
            $tags->getFirstValueByType(TagType::fromString(TagType::COMMENT)),
            self::extractSourceUrl($tags),
        );
    }

    private static function extractSourceUrl(TagCollection $tags): ?string
    {
        return array_find(
            $tags->getValuesByType(TagType::fromString(TagType::REFERENCE)),
            static fn (string $value): bool => str_starts_with($value, 'http://') || str_starts_with($value, 'https://'),
        );
    }

    public function toArray(): array
    {
        return [
            'context' => $this->context,
            'comment' => $this->comment,
            'source_url' => $this->sourceUrl,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['context'] ?? null,
            $data['comment'] ?? null,
            $data['source_url'] ?? null
        );
    }

    public function equals(self $other): bool
    {
        return $this->context === $other->context
            && $this->comment === $other->comment
            && $this->sourceUrl === $other->sourceUrl;
    }
}
