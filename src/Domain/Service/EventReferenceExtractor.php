<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReferences;
use Innis\Nostr\Core\Domain\ValueObject\Reference\QuoteAnalysis;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class EventReferenceExtractor implements EventReferenceExtractorInterface
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
            $contentReferences,
            $replyChain,
            $quoteAnalysis,
            new EventIdCollection($allEventIds)->unique(),
            new PublicKeyCollection($allPublicKeys)->unique()
        );
    }

    private static function analyseQuote(Event $event, ContentReferenceCollection $contentReferences): QuoteAnalysis
    {
        $isRepost = $event->isRepost();

        $hasQuoteTag = [] !== $event->getTags()->findByType(TagType::fromString(TagType::QUOTE));

        $hasEventInContent = array_any(
            $contentReferences->toArray(),
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

    /**
     * @return array{list<EventId>, list<PublicKey>}
     */
    private static function mergeAllReferences(
        TagReferences $tagReferences,
        ContentReferenceCollection $contentReferences,
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
