<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventContentTest extends TestCase
{
    public function testCanCreateFromString(): void
    {
        $content = EventContent::fromString('Hello Nostr!');

        $this->assertSame('Hello Nostr!', (string) $content);
        $this->assertSame('Hello Nostr!', (string) $content);
    }

    public function testTryFromStringIsNullForTextThatIsNotUtf8(): void
    {
        $this->assertNull(EventContent::tryFromString("caf\xC3"));
    }

    public function testTryFromStringKeepsUtf8TextAsWritten(): void
    {
        $this->assertSame("\u{FEFF}naïve 🔑", (string) EventContent::tryFromString("\u{FEFF}naïve 🔑"));
    }

    public function testFromStringThrowsForTextThatIsNotUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventContent::fromString("\xFF");
    }

    public function testCanCreateEmpty(): void
    {
        $content = EventContent::empty();

        $this->assertTrue($content->isEmpty());
        $this->assertSame('', (string) $content);
        $this->assertSame(0, $content->getLength());
    }

    public function testGetLengthWorksCorrectly(): void
    {
        $content = EventContent::fromString('Hello');

        $this->assertSame(5, $content->getLength());
    }

    public function testIsEmptyWorksCorrectly(): void
    {
        $emptyContent = EventContent::fromString('');
        $nonEmptyContent = EventContent::fromString('Hello');

        $this->assertTrue($emptyContent->isEmpty());
        $this->assertFalse($nonEmptyContent->isEmpty());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $content1 = EventContent::fromString('Hello');
        $content2 = EventContent::fromString('Hello');
        $content3 = EventContent::fromString('World');

        $this->assertTrue($content1->equals($content2));
        $this->assertFalse($content1->equals($content3));
    }

    public function testHandlesUnicodeCorrectly(): void
    {
        $content = EventContent::fromString('Hello 🌍');

        $this->assertSame('Hello 🌍', (string) $content);
        $this->assertSame(7, $content->getLength());
    }

    public function testExtractsSingleHashtag(): void
    {
        $content = EventContent::fromString('Hello #bob how are you?');

        $this->assertSame(['bob'], $content->extractHashtags()->toStrings());
    }

    public function testExtractsMultipleHashtags(): void
    {
        $content = EventContent::fromString('Testing #Bitcoin and #Nostr #FREEDOM');

        $this->assertSame(['bitcoin', 'nostr', 'freedom'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsReturnsEmptyArrayWhenNoHashtags(): void
    {
        $content = EventContent::fromString('No hashtags here');

        $this->assertEmpty($content->extractHashtags());
    }

    public function testExtractHashtagsConvertsToLowercase(): void
    {
        $content = EventContent::fromString('#Bitcoin #NOSTR #FrEeDoM');

        $this->assertSame(['bitcoin', 'nostr', 'freedom'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsRemovesDuplicates(): void
    {
        $content = EventContent::fromString('Duplicate #test #TEST #test');

        $this->assertCount(1, $content->extractHashtags());
        $this->assertSame(['test'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsHandlesNumericHashtags(): void
    {
        $content = EventContent::fromString('Edge case #123 and #456');

        $this->assertSame(['123', '456'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsHandlesUnderscores(): void
    {
        $content = EventContent::fromString('Testing #test_underscore and #another_one');

        $this->assertSame(['test_underscore', 'another_one'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsReadsLettersOfAnyScript(): void
    {
        $content = EventContent::fromString('Wir lieben #Zürich und #日本 und #Ελλάδα');

        $this->assertSame(['zürich', '日本', 'ελλάδα'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsKeepsCombiningMarks(): void
    {
        $content = EventContent::fromString("#cafe\u{0301} and #नमस्ते");

        $this->assertSame(["cafe\u{0301}", 'नमस्ते'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsIgnoresAHashAfterALetterOfAnyScript(): void
    {
        $content = EventContent::fromString('é#nottag and #tag');

        $this->assertSame(['tag'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsIgnoresAnHtmlEntity(): void
    {
        $content = EventContent::fromString('&#39; and #tag');

        $this->assertSame(['tag'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsIgnoresHashtagsInUrls(): void
    {
        $content = EventContent::fromString('Check https://example.com#anchor but also #realtag');

        $this->assertSame(['realtag'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsAtStartOfContent(): void
    {
        $content = EventContent::fromString('#first hashtag in content');

        $this->assertSame(['first'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsAtEndOfContent(): void
    {
        $content = EventContent::fromString('hashtag at the end #last');

        $this->assertSame(['last'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsMultipleInSequence(): void
    {
        $content = EventContent::fromString('#one #two #three #four #five');

        $this->assertSame(['one', 'two', 'three', 'four', 'five'], $content->extractHashtags()->toStrings());
    }

    public function testExtractHashtagsFromEmptyContent(): void
    {
        $content = EventContent::empty();

        $this->assertEmpty($content->extractHashtags());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function textWithUrls(): iterable
    {
        yield 'a fragment, a schemeless path and a word' => ['https://x.com/#frag x.com/#frag #a word#b', ['frag', 'a']];
        yield 'a fragment' => ['https://x.com/#frag', []];
        yield 'anything after the scheme separator' => ['see https://x.com/a.html#frag#more and wss://relay.example/?q=#x', []];
        yield 'whitespace ends the url' => ['https://x.com/#frag #after', ['after']];
        yield 'a hashtag before the scheme separator in its run' => ['#tag://x.com/#frag', ['tag']];
        yield 'any unicode whitespace ends the url' => ["https://x.com/\u{00A0}#after\u{3000}#again", ['after', 'again']];
        yield 'text without a scheme is no url' => ['x.com/#frag', ['frag']];
        yield 'a hashtag followed by a scheme separator' => ['#tag://x', ['tag']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('textWithUrls')]
    public function testExtractHashtagsLeavesOutTheTextOfAUrl(string $text, array $expected): void
    {
        $this->assertSame($expected, EventContent::fromString($text)->extractHashtags()->toStrings());
    }
}
