<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\WebUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;

final readonly class LiveEventMetadata
{
    private function __construct(
        private string $identifier,
        private ?string $title,
        private ?string $summary,
        private ?WebUrl $image,
        private ?string $status,
        private ?WebUrl $streaming,
    ) {
    }

    public static function tryFrom(
        string $identifier,
        ?string $title,
        ?string $summary,
        ?WebUrl $image,
        ?string $status,
        ?WebUrl $streaming,
    ): ?self {
        $isUtf8 = array_all(
            [$identifier, $title, $summary, $status],
            static fn (?string $text): bool => null === $text || mb_check_encoding($text, 'UTF-8'),
        );

        return $isUtf8 ? new self($identifier, $title, $summary, $image, $status, $streaming) : null;
    }

    public static function from(
        string $identifier,
        ?string $title,
        ?string $summary,
        ?WebUrl $image,
        ?string $status,
        ?WebUrl $streaming,
    ): self {
        return self::tryFrom($identifier, $title, $summary, $image, $status, $streaming)
            ?? throw new InvalidArgumentException('Live event identifier, title, summary and status must be UTF-8');
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

    public function getImage(): ?WebUrl
    {
        return $this->image;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStreaming(): ?WebUrl
    {
        return $this->streaming;
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
            WebUrl::tryFromString($tags->getSoleValueByType(TagType::fromString(TagType::IMAGE))->getValue()),
            $tags->getSoleValueByType(TagType::fromString(TagType::STATUS))->getValue(),
            WebUrl::tryFromString($tags->getSoleValueByType(TagType::fromString(TagType::STREAMING))->getValue()),
        );
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
            'status' => $this->status,
            'streaming' => $this->streaming?->__toString(),
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

        return self::tryFrom(
            $identifier,
            JsonWireFormat::stringField($data, 'title'),
            JsonWireFormat::stringField($data, 'summary'),
            WebUrl::tryFromString(JsonWireFormat::stringField($data, 'image')),
            JsonWireFormat::stringField($data, 'status'),
            WebUrl::tryFromString(JsonWireFormat::stringField($data, 'streaming')),
        );
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier
            && $this->title === $other->title
            && $this->summary === $other->summary
            && (null === $this->image ? null === $other->image : null !== $other->image && $this->image->equals($other->image))
            && $this->status === $other->status
            && (null === $this->streaming ? null === $other->streaming : null !== $other->streaming && $this->streaming->equals($other->streaming));
    }
}
