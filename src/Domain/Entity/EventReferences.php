<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class EventReferences
{
    public function __construct(
        private TagReferences $tagReferences,
        private array $contentReferences,
        private ReplyChain $replyChain,
        private QuoteAnalysis $quoteAnalysis,
        private array $allEventIds,
        private array $allPublicKeys,
    ) {
        foreach ($this->contentReferences as $reference) {
            if (!$reference instanceof ContentReference) {
                throw new InvalidArgumentException('All content references must be ContentReference instances');
            }
        }

        foreach ($this->allEventIds as $eventId) {
            if (!$eventId instanceof EventId) {
                throw new InvalidArgumentException('All event IDs must be EventId instances');
            }
        }

        foreach ($this->allPublicKeys as $publicKey) {
            if (!$publicKey instanceof PublicKey) {
                throw new InvalidArgumentException('All public keys must be PublicKey instances');
            }
        }
    }

    public function getTagReferences(): TagReferences
    {
        return $this->tagReferences;
    }

    public function getContentReferences(): array
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

    public function getAllEventIds(): array
    {
        return $this->allEventIds;
    }

    public function getAllPublicKeys(): array
    {
        return $this->allPublicKeys;
    }

    public function hasReferences(): bool
    {
        return !empty($this->tagReferences->getEvents())
            || !empty($this->tagReferences->getPubkeys())
            || !empty($this->tagReferences->getQuotes())
            || !empty($this->tagReferences->getAddressable())
            || !empty($this->contentReferences);
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
        return count($this->allEventIds);
    }

    public function getReferencedPubkeyCount(): int
    {
        return count($this->allPublicKeys);
    }

    public function toArray(): array
    {
        return [
            'tag_references' => $this->tagReferences->toArray(),
            'content_references' => array_map(
                static fn (ContentReference $ref) => $ref->toArray(),
                $this->contentReferences
            ),
            'reply_chain' => $this->replyChain->toArray(),
            'quote_analysis' => $this->quoteAnalysis->toArray(),
            'all_event_ids' => array_map(
                static fn (EventId $id) => $id->toHex(),
                $this->allEventIds
            ),
            'all_public_keys' => array_map(
                static fn (PublicKey $key) => $key->toHex(),
                $this->allPublicKeys
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
            TagReferences::fromArray($data['tag_references'] ?? []),
            $contentReferences,
            ReplyChain::fromArray($data['reply_chain']),
            QuoteAnalysis::fromArray($data['quote_analysis']),
            $eventIds,
            $publicKeys
        );
    }
}
