<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class LiveEventMetadata
{
    public function __construct(
        private string $identifier,
        private ?string $title,
        private ?string $summary,
        private ?string $image,
        private ?string $status,
        private ?string $streaming,
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStreaming(): ?string
    {
        return $this->streaming;
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
        $statusValues = $tags->getValuesByType(TagType::fromString('status'));
        $streamingValues = $tags->getValuesByType(TagType::fromString('streaming'));

        return new self(
            reset($identifierValues),
            !empty($titleValues) ? reset($titleValues) : null,
            !empty($summaryValues) ? reset($summaryValues) : null,
            !empty($imageValues) ? reset($imageValues) : null,
            !empty($statusValues) ? reset($statusValues) : null,
            !empty($streamingValues) ? reset($streamingValues) : null
        );
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'title' => $this->title,
            'summary' => $this->summary,
            'image' => $this->image,
            'status' => $this->status,
            'streaming' => $this->streaming,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['title'] ?? null,
            $data['summary'] ?? null,
            $data['image'] ?? null,
            $data['status'] ?? null,
            $data['streaming'] ?? null
        );
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier
            && $this->title === $other->title
            && $this->summary === $other->summary
            && $this->image === $other->image
            && $this->status === $other->status
            && $this->streaming === $other->streaming;
    }
}
