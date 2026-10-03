<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReferences;
use Innis\Nostr\Core\Domain\ValueObject\Reference\QuoteAnalysis;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class EventReferenceExtractor
{
    private function __construct()
    {
    }

    public static function extract(Event $event): EventReferences
    {
        $tagReferences = TagReferenceExtractor::extract($event->getTags());
        $contentReferences = ContentReferenceExtractor::extract($event->getContent());
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

        $hasQuoteTag = $event->getTags()->hasType(TagType::fromString(TagType::QUOTE));

        $hasQuoteInContent = array_any(
            $contentReferences->toArray(),
            static fn (ContentReference $ref): bool => $ref->isQuote(),
        );

        return new QuoteAnalysis(
            $hasQuoteTag,
            $hasQuoteInContent,
            $isRepost,
            $event->getKind()->is(EventKind::TEXT_NOTE),
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

        foreach ([...$tagReferences->getEvents(), ...$tagReferences->getQuotes()] as $ref) {
            $eventIds[] = $ref->getEventId();
            if (null !== $ref->getAuthor()) {
                $publicKeys[] = $ref->getAuthor();
            }
        }

        foreach ($tagReferences->getPubkeys() as $ref) {
            $publicKeys[] = $ref->getPubkey();
        }

        foreach ($contentReferences as $ref) {
            if (null !== $ref->getEventId()) {
                $eventIds[] = $ref->getEventId();
            }
            if (null !== $ref->getPublicKey()) {
                $publicKeys[] = $ref->getPublicKey();
            }
        }

        foreach ([$replyChain->getRoot(), $replyChain->getParent(), ...$replyChain->getMentionedEvents()] as $pointer) {
            if ($pointer instanceof EventReference) {
                $eventIds[] = $pointer->getEventId();
            }
        }

        foreach ($replyChain->getConversationParticipants() as $participant) {
            $publicKeys[] = $participant;
        }

        return [$eventIds, $publicKeys];
    }
}
