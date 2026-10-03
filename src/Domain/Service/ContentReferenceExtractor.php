<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;

final class ContentReferenceExtractor
{
    // Deliberate: an entity runs over every letter and digit up to the first other character or a following nostr:, never a fixed length, so one followed by more letters or digits is no reference rather than a shorter one — see ADR-0130
    private const string RUN = '(?:(?!nostr:)[a-z0-9])+';
    private const string STARTS_AT_A_BOUNDARY = '(?<![a-z0-9])';

    private function __construct()
    {
    }

    public static function extract(EventContent $content): ContentReferenceCollection
    {
        $references = [];
        $claimedOffsets = new ClaimedOffsets();

        $contentString = (string) $content;

        $patterns = [
            [ContentReferenceType::NostrUri, '/nostr:(?:npub1|nprofile1|note1|nevent1|naddr1)'.self::RUN.'/i'],
            [ContentReferenceType::BareNpub, '/'.self::STARTS_AT_A_BOUNDARY.'npub1'.self::RUN.'/i'],
            [ContentReferenceType::BareNote, '/'.self::STARTS_AT_A_BOUNDARY.'note1'.self::RUN.'/i'],
            [ContentReferenceType::BareNevent, '/'.self::STARTS_AT_A_BOUNDARY.'nevent1'.self::RUN.'/i'],
            [ContentReferenceType::BareNprofile, '/'.self::STARTS_AT_A_BOUNDARY.'nprofile1'.self::RUN.'/i'],
            [ContentReferenceType::BareNaddr, '/'.self::STARTS_AT_A_BOUNDARY.'naddr1'.self::RUN.'/i'],
        ];

        foreach ($patterns as [$type, $pattern]) {
            if (preg_match_all($pattern, $contentString, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    // Deliberate: overlap is checked by ClaimedOffsets, whose cost is the span's length and is counted by its test; scanning the claimed set instead made a 64KiB event take over five seconds — see ADR-0108
                    if (!$claimedOffsets->claim($match[1], strlen($match[0]))) {
                        continue;
                    }

                    $cleanRef = preg_replace('/^nostr:/i', '', $match[0]) ?? $match[0];
                    $decoded = Nip19Codec::decodeEntity($cleanRef);

                    // Deliberate: a run that does not decode whole is no reference, and no shorter entity is read from it — see ADR-0130
                    if (null === $decoded) {
                        continue;
                    }

                    $references[] = ContentReference::from($type, $match[0], $cleanRef, $match[1], $decoded);
                }
            }
        }

        usort($references, static fn (ContentReference $a, ContentReference $b): int => $a->getPosition() <=> $b->getPosition());

        return new ContentReferenceCollection($references);
    }
}
