<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class EventContent implements Stringable
{
    private const string NON_WHITESPACE_RUN = '/[^\p{Z}\t-\r\x{85}]+/u';
    private const string URL_SCHEME_SEPARATOR = '://';
    private const string HASHTAG = '/(?<![&\p{L}\p{M}\p{N}_])#([\p{L}\p{M}\p{N}_]+)/u';

    private function __construct(private string $content)
    {
    }

    public function isEmpty(): bool
    {
        return '' === $this->content;
    }

    public function getLength(): int
    {
        return mb_strlen($this->content, 'UTF-8');
    }

    public function equals(self $other): bool
    {
        return $this->content === $other->content;
    }

    public static function tryFromString(string $content): ?self
    {
        return mb_check_encoding($content, 'UTF-8') ? new self($content) : null;
    }

    public static function fromString(string $content): self
    {
        return self::tryFromString($content) ?? throw new InvalidArgumentException('Event content is UTF-8 text');
    }

    public static function empty(): self
    {
        return new self('');
    }

    // Deliberate: a # after the :// in its whitespace-delimited run is part of a URL, so each run is cut at its first :// before it is searched, never rescanned per # — see nostr-adrs ADR-0071
    public function extractHashtags(): HashtagCollection
    {
        preg_match_all(self::NON_WHITESPACE_RUN, $this->content, $runs);
        $words = array_merge(...array_map(self::hashtagWordsBeforeAnyUrl(...), $runs[0]));

        return new HashtagCollection(array_map(Hashtag::fromString(...), $words))->unique();
    }

    /**
     * @return list<string>
     */
    private static function hashtagWordsBeforeAnyUrl(string $run): array
    {
        preg_match_all(self::HASHTAG, explode(self::URL_SCHEME_SEPARATOR, $run, 2)[0], $matches);

        return $matches[1];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->content;
    }
}
