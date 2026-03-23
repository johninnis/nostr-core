<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class ContentReferenceTagBuilder
{
    public function __construct(
        private readonly ContentReferenceExtractorInterface $extractor,
    ) {
    }

    public function buildTags(EventContent $content, ?TagCollection $existingTags = null): TagCollection
    {
        $references = $this->extractor->extractContentReferences($content);
        $tags = $existingTags ?? TagCollection::empty();

        if (empty($references)) {
            return $tags;
        }

        $seenPubkeys = array_fill_keys($tags->getValuesByType(TagType::pubkey()), true);
        $seenEventIds = array_fill_keys($tags->getValuesByType(TagType::event()), true);

        foreach ($references as $ref) {
            if (!$ref instanceof ContentReference || $ref->hasError()) {
                continue;
            }

            $pubkey = $ref->getPublicKey();
            if ($pubkey !== null) {
                $pubkeyHex = $pubkey->toHex();
                if (!isset($seenPubkeys[$pubkeyHex])) {
                    $seenPubkeys[$pubkeyHex] = true;
                    $tags = $tags->add(Tag::pubkey($pubkeyHex));
                }
            }

            $eventId = $ref->getEventId();
            if ($eventId !== null) {
                $eventIdHex = $eventId->toHex();
                if (!isset($seenEventIds[$eventIdHex])) {
                    $seenEventIds[$eventIdHex] = true;
                    $authorHex = $pubkey?->toHex() ?? '';
                    $tags = $tags->add(Tag::fromArray(['q', $eventIdHex, '', $authorHex]));
                    $tags = $tags->add(Tag::event($eventIdHex, null, 'mention'));
                }
            }

            if ($ref->isAddressableReference()) {
                $refPubkey = $ref->getPublicKey();
                if ($refPubkey !== null) {
                    $coordinate = $ref->getKind() . ':' . $refPubkey->toHex() . ':' . $ref->getAddressableIdentifier();
                    $tags = $tags->add(Tag::fromArray(['a', $coordinate]));
                }
            }
        }

        return $tags;
    }
}
