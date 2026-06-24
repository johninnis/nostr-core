<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
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
        $identifier = $tags->getFirstValueByType(TagType::identifier());
        if (null === $identifier) {
            return null;
        }

        return new self(
            $identifier,
            $tags->getFirstValueByType(TagType::fromString(TagType::TITLE)),
            $tags->getFirstValueByType(TagType::fromString(TagType::SUMMARY)),
            $tags->getFirstValueByType(TagType::fromString(TagType::IMAGE)),
            $tags->getFirstValueByType(TagType::fromString(TagType::STATUS)),
            $tags->getFirstValueByType(TagType::fromString(TagType::STREAMING)),
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

    public static function fromArray(array $data): ?self
    {
        $identifier = JsonWireFormat::stringField($data, 'identifier');
        if (null === $identifier) {
            return null;
        }

        return new self(
            $identifier,
            JsonWireFormat::stringField($data, 'title'),
            JsonWireFormat::stringField($data, 'summary'),
            JsonWireFormat::stringField($data, 'image'),
            JsonWireFormat::stringField($data, 'status'),
            JsonWireFormat::stringField($data, 'streaming'),
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
