<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\EventKindCategory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class ContentReferenceTagBuilder
{
    private function __construct()
    {
    }

    public static function buildTags(EventContent $content, TagCollection $threadTags = new TagCollection()): TagCollection
    {
        $tags = $threadTags;

        foreach (self::contentTags($content) as $tag) {
            if (!in_array($tag->getValue(), $threadTags->getValuesByType($tag->getType()), true)) {
                $tags = $tags->add($tag);
            }
        }

        return $tags;
    }

    private static function contentTags(EventContent $content): TagCollection
    {
        $references = ContentReferenceExtractor::extract($content);
        $tags = new TagCollection();

        foreach ($references as $ref) {
            $pubkey = $ref->getPublicKey();
            if (null !== $pubkey) {
                $tags = self::withMention($tags, Tag::pubkey($pubkey));
            }

            $quoted = $ref->getQuotedTarget();
            if (null !== $quoted) {
                // Deliberate: every quoted entity is a q tag in the NIP-18 shape, never an e or an a, so it is not pulled into a thread as a reply — see ADR-0088
                $tags = self::withMention($tags, self::quoteTag($quoted, $ref));
            }
        }

        foreach ($content->extractHashtags() as $hashtag) {
            $tags = self::withMention($tags, Tag::hashtag($hashtag));
        }

        return $tags;
    }

    private static function withMention(TagCollection $tags, Tag $mention): TagCollection
    {
        $earlier = array_find(
            $tags->findByType($mention->getType()),
            static fn (Tag $tag): bool => $tag->getValue() === $mention->getValue(),
        );

        return $tags->add(null === $earlier ? $mention : self::keepingEarlierHints($earlier, $mention));
    }

    private static function keepingEarlierHints(Tag $earlier, Tag $later): Tag
    {
        $earlierElements = $earlier->toArray();
        $laterElements = $later->toArray();
        $merged = [];

        foreach (range(0, max(count($earlierElements), count($laterElements)) - 1) as $position) {
            $earlierElement = $earlierElements[$position] ?? '';
            $merged[] = '' !== $earlierElement ? $earlierElement : ($laterElements[$position] ?? '');
        }

        return Tag::fromArray($merged);
    }

    private static function quoteTag(EventId|EventCoordinate $quoted, ContentReference $quote): Tag
    {
        $relay = $quote->getRelays()->toStrings()[0] ?? '';
        $kind = $quote->getKind();
        $author = $quoted instanceof EventId && (null === $kind || EventKindCategory::Regular === $kind->category()) ? $quote->getPublicKey() : null;
        $target = $quoted instanceof EventId ? $quoted->toHex() : (string) $quoted;

        if (null !== $author) {
            return Tag::fromArray([TagType::QUOTE, $target, $relay, $author->toHex()]);
        }

        return Tag::fromArray('' === $relay ? [TagType::QUOTE, $target] : [TagType::QUOTE, $target, $relay]);
    }
}
