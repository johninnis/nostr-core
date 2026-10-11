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
    // Deliberate: a web URL runs to Unicode's first White_Space character, spelled out because PCRE's and JavaScript's \s differ — see nostr-adrs ADR-0107
    private const string WEB_URL = '~https?://[^\t\n\x{0B}\f\r \x{85}\x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]*~iu';

    private function __construct()
    {
    }

    public static function extract(EventContent $content): ContentReferenceCollection
    {
        $references = [];
        $claimedOffsets = new ClaimedOffsets();

        $contentString = (string) $content;
        $webUrls = self::webUrlSpans($contentString);

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
                    // Deliberate: a bare entity inside a web URL is part of the link, while a nostr: URI is a reference wherever it stands — see nostr-adrs ADR-0107
                    if (ContentReferenceType::NostrUri !== $type && self::isInsideAny($match[1], $webUrls)) {
                        continue;
                    }

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

    /**
     * @return list<array{int, int}>
     */
    private static function webUrlSpans(string $content): array
    {
        preg_match_all(self::WEB_URL, $content, $urls, PREG_OFFSET_CAPTURE);

        return array_map(static fn (array $url): array => [$url[1], $url[1] + strlen($url[0])], $urls[0]);
    }

    /**
     * @param list<array{int, int}> $spans
     */
    private static function isInsideAny(int $offset, array $spans): bool
    {
        return array_any($spans, static fn (array $span): bool => $offset >= $span[0] && $offset < $span[1]);
    }
}
