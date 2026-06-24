<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

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
            'content_references' => array_map(
                static fn (ContentReference $ref) => $ref->toArray(),
                $this->contentReferences->toArray()
            ),
            'reply_chain' => $this->replyChain->toArray(),
            'quote_analysis' => $this->quoteAnalysis->toArray(),
            'all_event_ids' => array_map(
                static fn (EventId $id) => $id->toHex(),
                $this->allEventIds->toArray()
            ),
            'all_public_keys' => array_map(
                static fn (PublicKey $key) => $key->toHex(),
                $this->allPublicKeys->toArray()
            ),
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

        $eventIds = [];
        if (isset($data['all_event_ids']) && is_array($data['all_event_ids'])) {
            $eventIds = array_values(array_filter(array_map(
                static fn (mixed $hex) => is_string($hex) ? EventId::fromHex($hex) : null,
                $data['all_event_ids']
            )));
        }

        $publicKeys = [];
        if (isset($data['all_public_keys']) && is_array($data['all_public_keys'])) {
            $publicKeys = array_values(array_filter(array_map(
                static fn (mixed $hex) => is_string($hex) ? PublicKey::fromHex($hex) : null,
                $data['all_public_keys']
            )));
        }

        return new self(
            TagReferences::fromArray(isset($data['tag_references']) && is_array($data['tag_references']) ? $data['tag_references'] : []),
            new ContentReferenceCollection($contentReferences),
            ReplyChain::fromArray(isset($data['reply_chain']) && is_array($data['reply_chain']) ? $data['reply_chain'] : []),
            QuoteAnalysis::fromArray(isset($data['quote_analysis']) && is_array($data['quote_analysis']) ? $data['quote_analysis'] : []),
            new EventIdCollection($eventIds),
            new PublicKeyCollection($publicKeys)
        );
    }
}
