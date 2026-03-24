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
        $contextValues = $tags->getValuesByType(TagType::fromString('context'));
        $commentValues = $tags->getValuesByType(TagType::fromString('comment'));
        $sourceUrl = self::extractSourceUrl($tags);

        return new self(
            !empty($contextValues) ? reset($contextValues) : null,
            !empty($commentValues) ? reset($commentValues) : null,
            $sourceUrl
        );
    }

    private static function extractSourceUrl(TagCollection $tags): ?string
    {
        $rValues = $tags->getValuesByType(TagType::fromString('r'));

        foreach ($rValues as $value) {
            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }
        }

        return null;
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
