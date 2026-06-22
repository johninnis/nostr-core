<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final class ContentReferenceTagBuilder implements ContentReferenceTagBuilderInterface
{
    public function __construct(
        private readonly ContentReferenceExtractorInterface $extractor,
    ) {
    }

    #[Override]
    public function buildTags(EventContent $content, ?TagCollection $existingTags = null): TagCollection
    {
        $references = $this->extractor->extractContentReferences($content);
        $tags = $existingTags ?? TagCollection::empty();

        foreach ($references as $ref) {
            $pubkey = $ref->getPublicKey();
            if (null !== $pubkey) {
                $tags = $tags->add(Tag::pubkey($pubkey->toHex()));
            }

            $eventId = $ref->getEventId();
            if (null !== $eventId) {
                $authorHex = $pubkey?->toHex() ?? '';
                // Deliberate: a quoted event emits a q tag only, no e mention, so it is not pulled into a thread as a reply — see ADR-0011
                $tags = $tags->add(Tag::create(TagType::QUOTE, $eventId->toHex(), '', $authorHex));
            }

            $kind = $ref->getKind();
            $addressableIdentifier = $ref->getAddressableIdentifier();
            if (null !== $pubkey && null !== $kind && null !== $addressableIdentifier) {
                $coordinate = EventCoordinate::fromParts($kind->toInt(), $pubkey->toHex(), $addressableIdentifier);
                if (null !== $coordinate) {
                    $tags = $tags->add(Tag::create(TagType::ADDRESSABLE, (string) $coordinate));
                }
            }
        }

        return $tags;
    }
}
