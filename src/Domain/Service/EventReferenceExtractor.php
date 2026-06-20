<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\EventReferences;
use Innis\Nostr\Core\Domain\Entity\QuoteAnalysis;
use Innis\Nostr\Core\Domain\Entity\ReplyChain;
use Innis\Nostr\Core\Domain\Entity\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventIdCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKeyCollection;
use Override;

final class EventReferenceExtractor implements EventReferenceExtractorInterface
{
    public function __construct(
        private ContentReferenceExtractorInterface $contentExtractor,
    ) {
    }

    #[Override]
    public function extractReferences(Event $event): EventReferences
    {
        $tagReferences = TagReferenceExtractor::extract($event->getTags());
        $contentReferences = $this->contentExtractor->extractContentReferences($event->getContent());
        $replyChain = ReplyChainAnalyser::analyse($event->getTags(), $event->getKind());
        $quoteAnalysis = self::analyseQuote($event);

        [$allEventIds, $allPublicKeys] = $this->mergeAllReferences(
            $tagReferences,
            $contentReferences,
            $replyChain
        );

        return new EventReferences(
            $tagReferences,
            new ContentReferenceCollection($contentReferences),
            $replyChain,
            $quoteAnalysis,
            new EventIdCollection($allEventIds),
            new PublicKeyCollection($allPublicKeys)
        );
    }

    private static function analyseQuote(Event $event): QuoteAnalysis
    {
        $isRepost = $event->getKind()->equals(EventKind::repost());

        $hasQuoteTag = array_any(
            $event->getTags()->toArray(),
            static fn (array $tagArray): bool => 'q' === $tagArray[0],
        );

        $hasEventInContent = 1 === preg_match(
            '/nostr:(note1[a-z0-9]{58}|nevent1[a-z0-9]+)/i',
            (string) $event->getContent()
        );

        $isQuote = $hasQuoteTag || ($event->getKind()->equals(EventKind::textNote()) && $hasEventInContent);

        return new QuoteAnalysis(
            $hasQuoteTag,
            $hasEventInContent,
            $isRepost,
            $isQuote
        );
    }

    private function mergeAllReferences(
        TagReferences $tagReferences,
        array $contentReferences,
        ReplyChain $replyChain,
    ): array {
        $eventIds = [];
        $publicKeys = [];

        foreach ($tagReferences->getEvents() as $ref) {
            $eventIds[] = $ref->getEventId();
            if (null !== $ref->getAuthor()) {
                $publicKeys[] = $ref->getAuthor();
            }
        }

        foreach ($tagReferences->getPubkeys() as $ref) {
            $publicKeys[] = $ref->getPubkey();
        }

        foreach ($tagReferences->getQuotes() as $ref) {
            $eventIds[] = $ref->getEventId();
            if (null !== $ref->getAuthor()) {
                $publicKeys[] = $ref->getAuthor();
            }
        }

        foreach ($contentReferences as $ref) {
            if (null !== $ref->getEventId()) {
                $eventIds[] = $ref->getEventId();
            }
            if (null !== $ref->getPublicKey()) {
                $publicKeys[] = $ref->getPublicKey();
            }
        }

        if (null !== $replyChain->getRootEvent()) {
            $eventIds[] = $replyChain->getRootEvent()->getEventId();
        }
        if (null !== $replyChain->getParentEvent()) {
            $eventIds[] = $replyChain->getParentEvent()->getEventId();
        }
        foreach ($replyChain->getMentionedEvents() as $mention) {
            $eventIds[] = $mention->getEventId();
        }
        foreach ($replyChain->getConversationParticipants() as $participant) {
            $publicKeys[] = $participant;
        }

        $uniqueEventIds = $this->deduplicateByHex($eventIds);
        $uniquePublicKeys = $this->deduplicateByHex($publicKeys);

        return [$uniqueEventIds, $uniquePublicKeys];
    }

    private function deduplicateByHex(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $unique[$item->toHex()] ??= $item;
        }

        return array_values($unique);
    }
}
