<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\EventReferences;
use Innis\Nostr\Core\Domain\Entity\QuoteAnalysis;
use Innis\Nostr\Core\Domain\Entity\ReplyChain;
use Innis\Nostr\Core\Domain\Entity\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;

final class EventReferenceExtractionService implements EventReferenceExtractionServiceInterface
{
    public function __construct(
        private ContentReferenceExtractorInterface $contentExtractor
    ) {
    }

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
            $contentReferences,
            $replyChain,
            $quoteAnalysis,
            $allEventIds,
            $allPublicKeys
        );
    }

    private static function analyseQuote(Event $event): QuoteAnalysis
    {
        $hasQuoteTag = false;
        $isRepost = $event->getKind()->toInt() === EventKind::REPOST;

        foreach ($event->getTags()->toArray() as $tagArray) {
            if ($tagArray[0] === 'q') {
                $hasQuoteTag = true;
                break;
            }
        }

        $hasEventInContent = preg_match(
            '/nostr:(note1[a-z0-9]{58}|nevent1[a-z0-9]+)/i',
            (string) $event->getContent()
        ) === 1;

        $isQuote = $hasQuoteTag || ($event->getKind()->toInt() === EventKind::TEXT_NOTE && $hasEventInContent);

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
        ReplyChain $replyChain
    ): array {
        $eventIds = [];
        $publicKeys = [];

        foreach ($tagReferences->getEvents() as $ref) {
            $eventIds[] = $ref->getEventId();
            if ($ref->getAuthor() !== null) {
                $publicKeys[] = $ref->getAuthor();
            }
        }

        foreach ($tagReferences->getPubkeys() as $ref) {
            $publicKeys[] = $ref->getPubkey();
        }

        foreach ($tagReferences->getQuotes() as $ref) {
            $eventIds[] = $ref->getEventId();
            if ($ref->getAuthor() !== null) {
                $publicKeys[] = $ref->getAuthor();
            }
        }

        foreach ($contentReferences as $ref) {
            if ($ref->getEventId() !== null) {
                $eventIds[] = $ref->getEventId();
            }
            if ($ref->getPublicKey() !== null) {
                $publicKeys[] = $ref->getPublicKey();
            }
        }

        if ($replyChain->getRootEvent() !== null) {
            $eventIds[] = $replyChain->getRootEvent()->getEventId();
        }
        if ($replyChain->getParentEvent() !== null) {
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
        $seen = [];

        foreach ($items as $item) {
            $hex = $item->toHex();
            if (!isset($seen[$hex])) {
                $unique[] = $item;
                $seen[$hex] = true;
            }
        }

        return $unique;
    }
}
