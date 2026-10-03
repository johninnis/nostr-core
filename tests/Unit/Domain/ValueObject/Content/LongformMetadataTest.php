<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\LongformMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LongformMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['d', 'my-article-slug'],
            ['title', 'My Article Title'],
            ['summary', 'A brief summary of the article'],
            ['image', 'https://example.com/image.jpg'],
            ['published_at', '1700000000'],
            ['t', 'nostr'],
            ['t', 'protocol'],
        ]);

        $metadata = LongformMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame('my-article-slug', $metadata->getIdentifier());
        $this->assertSame('My Article Title', $metadata->getTitle());
        $this->assertSame('A brief summary of the article', $metadata->getSummary());
        $this->assertSame('https://example.com/image.jpg', (string) $metadata->getImage());
        $this->assertNotNull($metadata->getPublishedAt());
        $this->assertSame(1700000000, $metadata->getPublishedAt()->toInt());
        $this->assertSame(['nostr', 'protocol'], $metadata->getTopics()->toStrings());
    }

    public function testFromTagCollectionWithOnlyIdentifier(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['d', 'minimal-article'],
        ]);

        $metadata = LongformMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame('minimal-article', $metadata->getIdentifier());
        $this->assertNull($metadata->getTitle());
        $this->assertNull($metadata->getSummary());
        $this->assertNull($metadata->getImage());
        $this->assertNull($metadata->getPublishedAt());
        $this->assertSame([], $metadata->getTopics()->toStrings());
    }

    public function testReadsAnEmptyTitleAndSummaryAsEmptyStrings(): void
    {
        $tags = TagCollectionMother::fromRaw([['d', 's'], ['title', ''], ['summary', '']]);

        $metadata = LongformMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame(['', ''], [$metadata->getTitle(), $metadata->getSummary()]);
    }

    public function testWritesAnEmptyTitleAndSummaryAsEmptyTags(): void
    {
        $metadata = LongformMetadata::from('s', '', '', null, null, new HashtagCollection());

        $this->assertSame([['d', 's'], ['title', ''], ['summary', '']], $metadata->toTags()->toJsonArray());
    }

    public function testTryFromTagCollectionHoldsTheImageInCanonicalForm(): void
    {
        $tags = TagCollectionMother::fromRaw([['d', 's'], ['image', 'HTTPS://Example.com:443/i.jpg#f']]);

        $this->assertSame('https://example.com/i.jpg', (string) LongformMetadata::tryFromTagCollection($tags)?->getImage());
    }

    #[DataProvider('imagesThatAreNotWebUrls')]
    public function testTryFromTagCollectionDropsAnImageThatIsNotAWebUrl(string $image): void
    {
        $tags = TagCollectionMother::fromRaw([['d', 's'], ['image', $image]]);

        $this->assertNull((LongformMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata'))->getImage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function imagesThatAreNotWebUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'ftp' => ['ftp://example.com/i.jpg'];
        yield 'data' => ['data:image/png;base64,AAAA'];
        yield 'text' => ['not a url'];
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArray(): void
    {
        $this->assertNull(LongformMetadata::tryFromArray('slug'));
    }

    public function testTryFromArrayDropsAStoredImageThatIsNotAWebUrl(): void
    {
        $restored = LongformMetadata::tryFromArray(['identifier' => 'slug', 'image' => 'thumbnail.jpg']);

        $this->assertNull($restored?->getImage());
    }

    public function testTreatsAMissingIdentifierAsEmpty(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['title', 'No Identifier Article'],
            ['t', 'nostr'],
        ]);

        $this->assertSame('', (LongformMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata'))->getIdentifier());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = LongformMetadata::from(
            'my-slug',
            'Title',
            'Summary',
            HttpUrl::fromString('https://example.com/img.jpg'),
            Timestamp::fromInt(1700000000),
            HashtagCollection::fromStrings(['nostr', 'dev'])
        );

        $array = $original->toArray();
        $restored = LongformMetadata::tryFromArray($array);

        $this->assertNotNull($restored);
        $this->assertTrue($original->equals($restored));
    }

    public function testTryFromArrayReturnsNullWhenIdentifierMissing(): void
    {
        $this->assertNull(LongformMetadata::tryFromArray(['title' => 'No identifier']));
    }

    public function testTryFromArrayIgnoresMalformedPublishedAtAndTopics(): void
    {
        $restored = LongformMetadata::tryFromArray([
            'identifier' => 'slug',
            'published_at' => 'not-an-int',
            'topics' => ['ok', 123, 'fine'],
        ]);

        $this->assertNotNull($restored);
        $this->assertNull($restored->getPublishedAt());
        $this->assertSame(['ok', 'fine'], $restored->getTopics()->toStrings());
    }

    /**
     * @param array{string, ?string, ?string} $text
     */
    #[DataProvider('textFieldsWithOneThatIsNotUtf8')]
    public function testTryFromRefusesATextFieldThatIsNotUtf8(array $text): void
    {
        $this->assertNull(LongformMetadata::tryFrom(...$text, image: null, publishedAt: null, topics: new HashtagCollection()));
    }

    /**
     * @return iterable<string, array{array{string, ?string, ?string}}>
     */
    public static function textFieldsWithOneThatIsNotUtf8(): iterable
    {
        yield 'identifier' => [["\xC3\x28", null, null]];
        yield 'title' => [['slug', "\xC3\x28", null]];
        yield 'summary' => [['slug', null, "\xFF"]];
    }

    public function testFromThrowsOnATextFieldThatIsNotUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LongformMetadata::from('slug', "\xFF", null, null, null, new HashtagCollection());
    }

    public function testTryFromArrayRefusesAnIdentifierThatIsNotUtf8(): void
    {
        $this->assertNull(LongformMetadata::tryFromArray(['identifier' => "\xC3\x28"]));
    }

    public function testTryFromArrayDropsATopicThatIsNotUtf8(): void
    {
        $restored = LongformMetadata::tryFromArray(['identifier' => 'slug', 'topics' => ["\xC3\x28", 'nostr']]);

        $this->assertSame(['nostr'], $restored?->getTopics()->toStrings());
    }

    public function testEveryArrayItAcceptsBecomesTags(): void
    {
        $restored = LongformMetadata::tryFromArray(['identifier' => 'slug', 'title' => 'Ünïcödé', 'topics' => ['ä']]);

        $this->assertCount(3, $restored?->toTags() ?? []);
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = LongformMetadata::from('slug', null, null, null, null, new HashtagCollection());

        $array = $original->toArray();
        $restored = LongformMetadata::tryFromArray($array);

        $this->assertNotNull($restored);
        $this->assertTrue($original->equals($restored));
    }

    public function testWritesARepeatedTopicOnce(): void
    {
        $metadata = LongformMetadata::from('slug', null, null, null, null, HashtagCollection::fromStrings(['nostr', 'dev', 'nostr']));

        $this->assertSame([['d', 'slug'], ['t', 'nostr'], ['t', 'dev']], $metadata->toTags()->toJsonArray());
    }

    public function testEquals(): void
    {
        $a = LongformMetadata::from('slug', 'Title', null, null, null, HashtagCollection::fromStrings(['nostr']));
        $b = LongformMetadata::from('slug', 'Title', null, null, null, HashtagCollection::fromStrings(['nostr']));
        $c = LongformMetadata::from('slug', 'Different', null, null, null, HashtagCollection::fromStrings(['nostr']));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testTryFromTagCollectionIsNullWhenDTagsDisagree(): void
    {
        $tags = new TagCollection([Tag::identifier('first'), Tag::identifier('second')]);

        $this->assertNull(LongformMetadata::tryFromTagCollection($tags));
    }

    public function testTryFromTagCollectionReadsARepeatedDTagAsOneIdentifier(): void
    {
        $tags = new TagCollection([Tag::identifier('slug'), Tag::identifier('slug')]);

        $this->assertSame('slug', LongformMetadata::tryFromTagCollection($tags)?->getIdentifier());
    }

    public function testTryFromTagCollectionReadsNoTitleFromTitleTagsThatDisagree(): void
    {
        $tags = new TagCollection([Tag::identifier('slug'), Tag::fromArray(['title', 'One']), Tag::fromArray(['title', 'Two'])]);

        $this->assertNull(LongformMetadata::tryFromTagCollection($tags)?->getTitle());
    }

    public function testTryFromTagCollectionReadsARepeatedTitleAsOneClaim(): void
    {
        $tags = new TagCollection([Tag::identifier('slug'), Tag::fromArray(['title', 'One']), Tag::fromArray(['title', 'One'])]);

        $this->assertSame('One', LongformMetadata::tryFromTagCollection($tags)?->getTitle());
    }
}
