<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventIdCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReferences;
use Innis\Nostr\Core\Domain\ValueObject\Reference\QuoteAnalysis;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
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
        $quoteAnalysis = self::analyseQuote($event, $contentReferences);

        [$allEventIds, $allPublicKeys] = self::mergeAllReferences(
            $tagReferences,
            $contentReferences,
            $replyChain
        );

        return new EventReferences(
            $tagReferences,
            new ContentReferenceCollection($contentReferences),
            $replyChain,
            $quoteAnalysis,
            new EventIdCollection($allEventIds)->unique(),
            new PublicKeyCollection($allPublicKeys)->unique()
        );
    }

    private static function analyseQuote(Event $event, array $contentReferences): QuoteAnalysis
    {
        $isRepost = $event->getKind()->is(EventKind::REPOST);

        $hasQuoteTag = array_any(
            $event->getTags()->toJsonArray(),
            static fn (array $tagArray): bool => TagType::QUOTE === $tagArray[0],
        );

        $hasEventInContent = array_any(
            $contentReferences,
            static fn (ContentReference $ref): bool => ContentReferenceType::NostrUri === $ref->getType() && $ref->isEventReference(),
        );

        $isQuote = $hasQuoteTag || ($event->getKind()->is(EventKind::TEXT_NOTE) && $hasEventInContent);

        return new QuoteAnalysis(
            $hasQuoteTag,
            $hasEventInContent,
            $isRepost,
            $isQuote
        );
    }

    private static function mergeAllReferences(
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

        return [$eventIds, $publicKeys];
    }
}
