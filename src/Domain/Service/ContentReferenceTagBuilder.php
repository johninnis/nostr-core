<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;

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

        foreach ($references as $ref) {
            if (!$ref instanceof ContentReference || $ref->hasError()) {
                continue;
            }

            $pubkey = $ref->getPublicKey();
            if (null !== $pubkey) {
                $tags = $tags->add(Tag::pubkey($pubkey->toHex()));
            }

            $eventId = $ref->getEventId();
            if (null !== $eventId) {
                $authorHex = $pubkey?->toHex() ?? '';
                $tags = $tags->add(Tag::fromArray(['q', $eventId->toHex(), '', $authorHex]));
                $tags = $tags->add(Tag::event($eventId->toHex(), null, 'mention'));
            }

            if ($ref->isAddressableReference()) {
                $refPubkey = $ref->getPublicKey();
                if (null !== $refPubkey) {
                    $coordinate = $ref->getKind().':'.$refPubkey->toHex().':'.$ref->getAddressableIdentifier();
                    $tags = $tags->add(Tag::fromArray(['a', $coordinate]));
                }
            }
        }

        return $tags;
    }
}
