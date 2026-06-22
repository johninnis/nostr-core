<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

final readonly class QuoteAnalysis
{
    public function __construct(
        private bool $hasQuoteTag,
        private bool $hasEventInContent,
        private bool $isRepost,
        private bool $isQuote,
    ) {
    }

    public function hasQuoteTag(): bool
    {
        return $this->hasQuoteTag;
    }

    public function hasEventInContent(): bool
    {
        return $this->hasEventInContent;
    }

    public function isRepost(): bool
    {
        return $this->isRepost;
    }

    public function isQuote(): bool
    {
        return $this->isQuote;
    }

    public function toArray(): array
    {
        return [
            'has_quote_tag' => $this->hasQuoteTag,
            'has_event_in_content' => $this->hasEventInContent,
            'is_repost' => $this->isRepost,
            'is_quote' => $this->isQuote,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['has_quote_tag'] ?? false),
            (bool) ($data['has_event_in_content'] ?? false),
            (bool) ($data['is_repost'] ?? false),
            (bool) ($data['is_quote'] ?? false)
        );
    }
}
