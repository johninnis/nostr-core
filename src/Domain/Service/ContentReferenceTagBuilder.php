<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
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
                $tags = $tags->add(Tag::fromArray([TagType::QUOTE, $eventId->toHex(), '', $authorHex]));
                $tags = $tags->add(Tag::event($eventId->toHex(), null, Nip10Marker::Mention->value));
            }

            if ($ref->isAddressableReference()) {
                $kind = $ref->getKind();
                if (null !== $pubkey && null !== $kind) {
                    $coordinate = $kind->toInt().':'.$pubkey->toHex().':'.$ref->getAddressableIdentifier();
                    $tags = $tags->add(Tag::fromArray([TagType::ADDRESSABLE, $coordinate]));
                }
            }
        }

        return $tags;
    }
}
