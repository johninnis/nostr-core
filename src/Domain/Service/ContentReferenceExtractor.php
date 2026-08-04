<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Override;

final readonly class ContentReferenceExtractor implements ContentReferenceExtractorInterface
{
    public function __construct(
        private Nip19CodecInterface $nip19Codec,
    ) {
    }

    #[Override]
    public function extractContentReferences(EventContent $content): ContentReferenceCollection
    {
        $references = [];

        /** @var array<int, true> $claimedOffsets */
        $claimedOffsets = [];

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

                    $span = array_fill($position, $length, true);

                    // Deliberate: the short span is the FIRST argument — array_intersect_key iterates its first array, so testing the claimed set against the span instead would scan every offset claimed so far and make this quadratic in the match count; a 64KiB event of adjacent references took over five seconds that way
                    if ([] !== array_intersect_key($span, $claimedOffsets)) {
                        continue;
                    }

                    $cleanRef = preg_replace('/^nostr:/i', '', $match[0]) ?? $match[0];

                    $references[] = new ContentReference(
                        $type,
                        $match[0],
                        $cleanRef,
                        $match[1],
                        $this->nip19Codec->decodeComplexEntity($cleanRef),
                    );

                    $claimedOffsets += $span;
                }
            }
        }

        usort($references, static fn (ContentReference $a, ContentReference $b): int => $a->getPosition() <=> $b->getPosition());

        return new ContentReferenceCollection($references);
    }
}
