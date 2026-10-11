<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;

final readonly class HighlightMetadata
{
    private const string SOURCE_MARKER = 'source';

    private const string MENTION_MARKER = 'mention';

    private function __construct(
        private ?string $context,
        private ?string $comment,
        private ?HttpUrl $sourceUrl,
    ) {
    }

    public static function tryFrom(?string $context, ?string $comment, ?HttpUrl $sourceUrl): ?self
    {
        $isUtf8 = array_all(
            [$context, $comment],
            static fn (?string $text): bool => null === $text || mb_check_encoding($text, 'UTF-8'),
        );

        return $isUtf8 ? new self($context, $comment, $sourceUrl) : null;
    }

    public static function from(?string $context, ?string $comment, ?HttpUrl $sourceUrl): self
    {
        return self::tryFrom($context, $comment, $sourceUrl)
            ?? throw new InvalidArgumentException('Highlight context and comment must be UTF-8');
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getSourceUrl(): ?HttpUrl
    {
        return $this->sourceUrl;
    }

    public static function fromTagCollection(TagCollection $tags): self
    {
        return self::from(
            $tags->getSoleValueByType(TagType::fromString(TagType::CONTEXT))->getValue(),
            $tags->getSoleValueByType(TagType::fromString(TagType::COMMENT))->getValue(),
            self::extractSourceUrl($tags),
        );
    }

    private static function extractSourceUrl(TagCollection $tags): ?HttpUrl
    {
        $webPages = array_values(array_filter(
            $tags->findByType(TagType::fromString(TagType::REFERENCE)),
            static fn (Tag $tag): bool => null !== self::canonicalUrl($tag->getValue() ?? ''),
        ));
        $marked = array_values(array_filter($webPages, static fn (Tag $tag): bool => self::SOURCE_MARKER === $tag->getValue(1)));
        $candidates = [] !== $marked
            ? $marked
            : array_values(array_filter($webPages, static fn (Tag $tag): bool => self::MENTION_MARKER !== $tag->getValue(1)));

        return HttpUrl::tryFromString(SoleTagValue::fromValues(
            array_map(static fn (Tag $tag): string => $tag->getValue() ?? '', $candidates),
            self::canonicalUrl(...),
        )->getValue());
    }

    private static function canonicalUrl(string $url): ?string
    {
        return HttpUrl::tryFromString($url)?->__toString();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'context' => $this->context,
            'comment' => $this->comment,
            'source_url' => null === $this->sourceUrl ? null : (string) $this->sourceUrl,
        ];
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        return self::tryFrom(
            JsonWireFormat::stringField($data, 'context'),
            JsonWireFormat::stringField($data, 'comment'),
            HttpUrl::tryFromString(JsonWireFormat::stringField($data, 'source_url')),
        );
    }

    public function equals(self $other): bool
    {
        return $this->context === $other->context
            && $this->comment === $other->comment
            && (null === $this->sourceUrl ? null === $other->sourceUrl : null !== $other->sourceUrl && $this->sourceUrl->equals($other->sourceUrl));
    }
}
