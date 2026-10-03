<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

final readonly class QuoteAnalysis
{
    public function __construct(
        private bool $hasQuoteTag,
        private bool $hasQuoteInContent,
        private bool $isRepost,
        private bool $isShortNote,
    ) {
    }

    public function hasQuoteTag(): bool
    {
        return $this->hasQuoteTag;
    }

    public function hasQuoteInContent(): bool
    {
        return $this->hasQuoteInContent;
    }

    public function isRepost(): bool
    {
        return $this->isRepost;
    }

    public function isShortNote(): bool
    {
        return $this->isShortNote;
    }

    public function isQuote(): bool
    {
        return $this->hasQuoteTag || ($this->isShortNote && $this->hasQuoteInContent);
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'has_quote_tag' => $this->hasQuoteTag,
            'has_quote_in_content' => $this->hasQuoteInContent,
            'is_repost' => $this->isRepost,
            'is_short_note' => $this->isShortNote,
            'is_quote' => $this->isQuote(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            JsonWireFormat::boolField($data, 'has_quote_tag') ?? false,
            JsonWireFormat::boolField($data, 'has_quote_in_content') ?? false,
            JsonWireFormat::boolField($data, 'is_repost') ?? false,
            JsonWireFormat::boolField($data, 'is_short_note') ?? false,
        );
    }
}
