<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;

final readonly class EventReferences
{
    public function __construct(
        private TagReferences $tagReferences,
        private ContentReferenceCollection $contentReferences,
        private ReplyChain $replyChain,
        private QuoteAnalysis $quoteAnalysis,
        private EventIdCollection $allEventIds,
        private PublicKeyCollection $allPublicKeys,
    ) {
    }

    public function getTagReferences(): TagReferences
    {
        return $this->tagReferences;
    }

    public function getContentReferences(): ContentReferenceCollection
    {
        return $this->contentReferences;
    }

    public function getReplyChain(): ReplyChain
    {
        return $this->replyChain;
    }

    public function getQuoteAnalysis(): QuoteAnalysis
    {
        return $this->quoteAnalysis;
    }

    public function getAllEventIds(): EventIdCollection
    {
        return $this->allEventIds;
    }

    public function getAllPublicKeys(): PublicKeyCollection
    {
        return $this->allPublicKeys;
    }

    public function hasReferences(): bool
    {
        return !$this->tagReferences->getEvents()->isEmpty()
            || !$this->tagReferences->getPubkeys()->isEmpty()
            || !$this->tagReferences->getQuotes()->isEmpty()
            || !$this->tagReferences->getAddressable()->isEmpty()
            || !$this->contentReferences->isEmpty();
    }

    public function isReply(): bool
    {
        return $this->replyChain->isReply();
    }

    public function isQuote(): bool
    {
        return $this->quoteAnalysis->isQuote();
    }

    public function getReferencedEventCount(): int
    {
        return $this->allEventIds->count();
    }

    public function getReferencedPubkeyCount(): int
    {
        return $this->allPublicKeys->count();
    }

    public function toArray(): array
    {
        return [
            'tag_references' => $this->tagReferences->toArray(),
            'content_references' => $this->contentReferences->toJsonArray(),
            'reply_chain' => $this->replyChain->toArray(),
            'quote_analysis' => $this->quoteAnalysis->toArray(),
            'all_event_ids' => $this->allEventIds->toHexes(),
            'all_public_keys' => $this->allPublicKeys->toHexes(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $contentReferences = [];
        if (isset($data['content_references']) && is_array($data['content_references'])) {
            $contentReferences = array_values(array_filter(array_map(
                static fn (mixed $refData) => is_array($refData) ? ContentReference::fromArray($refData) : null,
                $data['content_references']
            )));
        }

        return new self(
            TagReferences::fromArray(isset($data['tag_references']) && is_array($data['tag_references']) ? $data['tag_references'] : []),
            new ContentReferenceCollection($contentReferences),
            ReplyChain::fromArray(isset($data['reply_chain']) && is_array($data['reply_chain']) ? $data['reply_chain'] : []),
            QuoteAnalysis::fromArray(isset($data['quote_analysis']) && is_array($data['quote_analysis']) ? $data['quote_analysis'] : []),
            EventIdCollection::fromHexValues($data['all_event_ids'] ?? null),
            PublicKeyCollection::fromHexValues($data['all_public_keys'] ?? null)
        );
    }
}
