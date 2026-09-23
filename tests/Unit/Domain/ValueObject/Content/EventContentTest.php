<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use PHPUnit\Framework\TestCase;

final class EventContentTest extends TestCase
{
    public function testCanCreateFromString(): void
    {
        $content = EventContent::fromString('Hello Nostr!');

        $this->assertSame('Hello Nostr!', (string) $content);
        $this->assertSame('Hello Nostr!', (string) $content);
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
        $this->assertSame(7, $content->getLength()); // UTF-8 character count, not byte count
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
}
