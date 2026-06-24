<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Override;

final class ContentReferenceExtractor implements ContentReferenceExtractorInterface
{
    public function __construct(
        private Nip19CodecInterface $nip19Codec,
    ) {
    }

    #[Override]
    public function extractContentReferences(EventContent $content): ContentReferenceCollection
    {
        $references = [];
        $usedPositions = [];

        $contentString = (string) $content;

        /** @var list<array{ContentReferenceType, string}> $patterns */
        $patterns = [
            [ContentReferenceType::NostrUri, '/nostr:(npub1[a-z0-9]{58}|nprofile1[a-z0-9]+|note1[a-z0-9]{58}|nevent1[a-z0-9]+|naddr1[a-z0-9]+)/i'],
            [ContentReferenceType::BareNpub, '/(?<![a-z0-9])npub1[a-z0-9]{58}(?=nostr:|[^a-z0-9]|$)/i'],
            [ContentReferenceType::BareNote, '/(?<![a-z0-9])note1[a-z0-9]{58}(?=nostr:|[^a-z0-9]|$)/i'],
            [ContentReferenceType::BareNevent, '/(?<![a-z0-9])nevent1(?:(?!nostr:)[a-z0-9])+(?=nostr:|[^a-z0-9]|$)/i'],
            [ContentReferenceType::BareNprofile, '/(?<![a-z0-9])nprofile1(?:(?!nostr:)[a-z0-9])+(?=nostr:|[^a-z0-9]|$)/i'],
            [ContentReferenceType::BareNaddr, '/(?<![a-z0-9])naddr1(?:(?!nostr:)[a-z0-9])+(?=nostr:|[^a-z0-9]|$)/i'],
            [ContentReferenceType::LegacyRef, '/#\[(\d+)\]/'],
        ];

        foreach ($patterns as [$type, $pattern]) {
            if (preg_match_all($pattern, $contentString, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $position = $match[1];
                    $length = strlen($match[0]);

                    $overlaps = array_any(
                        $usedPositions,
                        static fn (array $usedRange): bool => $position < $usedRange['end'] && $position + $length > $usedRange['start'],
                    );

                    if (!$overlaps) {
                        $cleanRef = str_replace('nostr:', '', $match[0]);

                        $references[] = new ContentReference(
                            $type,
                            $match[0],
                            $cleanRef,
                            $match[1],
                            $this->nip19Codec->decodeComplexEntity($cleanRef),
                        );

                        $usedPositions[] = ['start' => $position, 'end' => $position + $length];
                    }
                }
            }
        }

        usort($references, static fn (ContentReference $a, ContentReference $b): int => $a->getPosition() <=> $b->getPosition());

        return new ContentReferenceCollection($references);
    }
}
