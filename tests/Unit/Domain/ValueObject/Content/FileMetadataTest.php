<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FileMetadataTest extends TestCase
{
    public function testTryFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['url', 'https://cdn.example.com/abc.png'],
            ['m', 'image/png'],
            ['x', str_repeat('a', 64)],
            ['ox', str_repeat('b', 64)],
            ['size', '4096'],
            ['dim', '800x600'],
            ['blurhash', 'LEHV6nWB2yk8'],
            ['thumb', 'https://cdn.example.com/abc-thumb.png'],
            ['image', 'https://cdn.example.com/abc-preview.png'],
            ['summary', 'A picture'],
            ['alt', 'Accessible description'],
            ['fallback', 'https://mirror.example.com/abc.png'],
            ['fallback', 'https://mirror2.example.com/abc.png'],
        ]);

        $metadata = FileMetadata::tryFromTagCollection($tags);

        self::assertNotNull($metadata);
        self::assertSame('https://cdn.example.com/abc.png', $metadata->getUrl());
        self::assertSame('image/png', $metadata->getMimeType());
        self::assertSame(str_repeat('a', 64), $metadata->getHash());
        self::assertSame(str_repeat('b', 64), $metadata->getOriginalHash());
        self::assertSame(4096, $metadata->getSize());
        self::assertSame('800x600', $metadata->getDimensions());
        self::assertSame('LEHV6nWB2yk8', $metadata->getBlurhash());
        self::assertSame('https://cdn.example.com/abc-thumb.png', $metadata->getThumbnail());
        self::assertSame('https://cdn.example.com/abc-preview.png', $metadata->getImage());
        self::assertSame('A picture', $metadata->getSummary());
        self::assertSame('Accessible description', $metadata->getAlt());
        self::assertSame([
            'https://mirror.example.com/abc.png',
            'https://mirror2.example.com/abc.png',
        ], $metadata->getFallbacks());
    }

    public function testTryFromTagCollectionReturnsNullWithoutUrl(): void
    {
        $tags = TagCollectionMother::fromRaw([['m', 'image/png']]);

        self::assertNull(FileMetadata::tryFromTagCollection($tags));
    }

    public function testTryFromTagCollectionIgnoresNonNumericSize(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['url', 'https://cdn.example.com/abc.png'],
            ['size', 'not-a-number'],
        ]);

        $metadata = FileMetadata::tryFromTagCollection($tags);

        self::assertNotNull($metadata);
        self::assertNull($metadata->getSize());
    }

    #[DataProvider('sizesThatAreNotANonNegativeDecimalInteger')]
    public function testTryFromTagCollectionIgnoresASizeThatIsNotANonNegativeDecimalInteger(string $size): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['size', $size]]);

        self::assertNull(FileMetadata::tryFromTagCollection($tags)?->getSize());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sizesThatAreNotANonNegativeDecimalInteger(): iterable
    {
        yield 'fraction' => ['1.5'];
        yield 'exponent' => ['1e3'];
        yield 'negative' => ['-5'];
        yield 'explicit sign' => ['+5'];
        yield 'leading space' => [' 5'];
        yield 'empty' => [''];
    }

    public function testTryFromTagCollectionReadsASizeOfZero(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['size', '0']]);

        self::assertSame(0, FileMetadata::tryFromTagCollection($tags)?->getSize());
    }

    public function testTryFromTagCollectionReturnsNullForAnEmptyUrl(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', ''], ['m', 'image/png']]);

        self::assertNull(FileMetadata::tryFromTagCollection($tags));
    }

    public function testTryFromImetaTagReturnsNullForAnEmptyUrl(): void
    {
        self::assertNull(FileMetadata::tryFromImetaTag(Tag::fromArray(['imeta', 'url ', 'alt a picture'])));
    }

    public function testTryFromTagCollectionReadsAMimeTypeWrittenInAnyCaseAsLowercase(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['m', 'Image/PNG']]);

        self::assertSame('image/png', FileMetadata::tryFromTagCollection($tags)?->getMimeType());
    }

    public function testTryFromTagCollectionReadsNoMimeTypeFromAValueThatIsNotOneAndKeepsTheRest(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['m', 'png'], ['alt', 'a picture']]);
        $metadata = FileMetadata::tryFromTagCollection($tags);

        self::assertSame([null, 'a picture'], [$metadata?->getMimeType(), $metadata?->getAlt()]);
    }

    public function testTryFromImetaTagReadsNoMimeTypeFromAValueThatIsNotOne(): void
    {
        $tag = Tag::fromArray(['imeta', 'url https://cdn.example.com/abc.png', 'm image/png; charset=utf-8', 'alt a picture']);

        self::assertNull(FileMetadata::tryFromImetaTag($tag)?->getMimeType());
    }

    public function testFromThrowsForAnEmptyUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileMetadata::from('', 'image/png');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mimeTypesNotInCanonicalForm(): iterable
    {
        yield 'empty' => [''];
        yield 'no subtype' => ['png'];
        yield 'a parameter' => ['text/plain; charset=utf-8'];
        yield 'uppercase' => ['image/PNG'];
    }

    #[DataProvider('mimeTypesNotInCanonicalForm')]
    public function testFromThrowsForAMimeTypeNotInCanonicalForm(string $mimeType): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileMetadata::from('https://cdn.example.com/abc.png', $mimeType);
    }

    public function testFromNamesTheEmptyUrlItRefuses(): void
    {
        $this->expectExceptionMessage('File metadata names the URL to download the file from, and an empty URL names none');

        FileMetadata::from('', 'image/png');
    }

    public function testFromNamesTheMimeTypeItRefuses(): void
    {
        $this->expectExceptionMessage('File metadata states its MIME type as a lowercase type/subtype, not "image/PNG"');

        FileMetadata::from('https://cdn.example.com/abc.png', 'image/PNG');
    }

    public function testFromNamesTheSizeItRefuses(): void
    {
        $this->expectExceptionMessage('File metadata states its size as a whole number of bytes, not -1');

        FileMetadata::from('https://cdn.example.com/abc.png', size: -1);
    }

    public function testTryFromTagCollectionKeepsAnEmptyOptionalField(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['alt', '']]);

        self::assertSame('', FileMetadata::tryFromTagCollection($tags)?->getAlt());
    }

    public function testTryFromImetaTagKeepsAnEmptyOptionalField(): void
    {
        $tag = Tag::fromArray(['imeta', 'url https://cdn.example.com/abc.png', 'alt ']);

        self::assertSame('', FileMetadata::tryFromImetaTag($tag)?->getAlt());
    }

    public function testToTagsWritesAnEmptyOptionalField(): void
    {
        $metadata = FileMetadata::from(url: 'https://cdn.example.com/abc.png', alt: '');

        self::assertSame([['url', 'https://cdn.example.com/abc.png'], ['alt', '']], $metadata->toTags()->toJsonArray());
    }

    public function testTryFromTagCollectionReadsARepeatedFieldAsOneClaim(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['m', 'image/png'], ['m', 'image/png']]);

        self::assertSame('image/png', FileMetadata::tryFromTagCollection($tags)?->getMimeType());
    }

    public function testTryFromTagCollectionReadsNoValueFromFieldsThatDisagree(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png'], ['m', 'image/png'], ['m', 'image/jpeg']]);

        self::assertNull(FileMetadata::tryFromTagCollection($tags)?->getMimeType());
    }

    public function testTryFromTagCollectionReturnsNullWhenUrlsDisagree(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/a.png'], ['url', 'https://cdn.example.com/b.png']]);

        self::assertNull(FileMetadata::tryFromTagCollection($tags));
    }

    public function testTryFromTagCollectionStaysLenientAboutFieldsNip94Requires(): void
    {
        $tags = TagCollectionMother::fromRaw([['url', 'https://cdn.example.com/abc.png']]);

        self::assertSame('https://cdn.example.com/abc.png', FileMetadata::tryFromTagCollection($tags)?->getUrl());
    }

    public function testToTagsRoundTrips(): void
    {
        $metadata = FileMetadata::from(
            url: 'https://cdn.example.com/abc.png',
            mimeType: 'image/png',
            hash: str_repeat('a', 64),
            size: 4096,
            fallbacks: ['https://mirror.example.com/abc.png'],
        );

        $restored = FileMetadata::tryFromTagCollection($metadata->toTags());

        self::assertNotNull($restored);
        self::assertTrue($metadata->equals($restored));
    }

    public function testToTagsOmitsAbsentFields(): void
    {
        $metadata = FileMetadata::from(url: 'https://cdn.example.com/abc.png');
        $tags = $metadata->toTags();

        self::assertCount(1, $tags);
        foreach ($tags as $tag) {
            self::assertSame('url', (string) $tag->getType());
        }
    }

    public function testImetaTagRoundTrips(): void
    {
        $metadata = FileMetadata::from(
            url: 'https://cdn.example.com/abc.png',
            mimeType: 'image/png',
            hash: str_repeat('a', 64),
            dimensions: '800x600',
        );

        $tag = $metadata->toImetaTag();

        self::assertNotNull($tag);
        self::assertSame('imeta', (string) $tag->getType());

        $restored = FileMetadata::tryFromImetaTag($tag);

        self::assertNotNull($restored);
        self::assertTrue($metadata->equals($restored));
    }

    public function testTryFromImetaTagRejectsNonImetaTag(): void
    {
        $tag = Tag::fromArray(['e', 'url https://cdn.example.com/abc.png']);

        self::assertNull(FileMetadata::tryFromImetaTag($tag));
    }

    public function testToImetaTagIsNullForMetadataHoldingOnlyAUrl(): void
    {
        self::assertNull(FileMetadata::from('https://cdn.example.com/abc.png')->toImetaTag());
    }

    public function testTryFromImetaTagRefusesATagHoldingOnlyAUrl(): void
    {
        self::assertNull(FileMetadata::tryFromImetaTag(Tag::fromArray(['imeta', 'url https://cdn.example.com/abc.png'])));
    }

    public function testTryFromImetaTagAcceptsAUrlAndOneOtherField(): void
    {
        $tag = Tag::fromArray(['imeta', 'url https://cdn.example.com/abc.png', 'alt a picture']);

        self::assertSame('a picture', FileMetadata::tryFromImetaTag($tag)?->getAlt());
    }

    /**
     * @param list<string> $otherFields
     */
    #[DataProvider('fieldsThatAreNotKept')]
    public function testTryFromImetaTagRefusesATagWhoseOnlyValidFieldIsTheUrl(array $otherFields): void
    {
        self::assertNull(FileMetadata::tryFromImetaTag(Tag::fromArray(['imeta', 'url https://cdn.example.com/abc.png', ...$otherFields])));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function fieldsThatAreNotKept(): iterable
    {
        yield 'a size that is not a decimal integer' => [['size abc']];
        yield 'a MIME type that is not one' => [['m not-a-mime-type']];
        yield 'two disagreeing dimensions' => [['dim 1x1', 'dim 2x2']];
        yield 'an entry with no value' => [['alt']];
    }

    public function testFromThrowsForANegativeSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileMetadata::from('https://cdn.example.com/abc.png', size: -1);
    }

    public function testTryFromIsNullForAnEmptyUrl(): void
    {
        self::assertNull(FileMetadata::tryFrom(''));
    }

    #[DataProvider('mimeTypesNotInCanonicalForm')]
    public function testTryFromIsNullForAMimeTypeNotInCanonicalForm(string $mimeType): void
    {
        self::assertNull(FileMetadata::tryFrom('https://cdn.example.com/abc.png', $mimeType));
    }

    public function testTryFromIsNullForANegativeSize(): void
    {
        self::assertNull(FileMetadata::tryFrom('https://cdn.example.com/abc.png', size: -1));
    }

    /**
     * @param array{url: string, hash?: string, originalHash?: string, dimensions?: string, blurhash?: string, thumbnail?: string, image?: string, summary?: string, alt?: string, fallbacks?: list<string>} $arguments
     */
    #[DataProvider('metadataHoldingANonUtf8String')]
    public function testTryFromIsNullForAFieldThatIsNotUtf8(array $arguments): void
    {
        self::assertNull(FileMetadata::tryFrom(...$arguments));
    }

    /**
     * @param array{url: string, hash?: string, originalHash?: string, dimensions?: string, blurhash?: string, thumbnail?: string, image?: string, summary?: string, alt?: string, fallbacks?: list<string>} $arguments
     */
    #[DataProvider('metadataHoldingANonUtf8String')]
    public function testFromNamesAFieldThatIsNotUtf8(array $arguments): void
    {
        $this->expectExceptionMessage('File metadata is UTF-8 text');

        FileMetadata::from(...$arguments);
    }

    /**
     * @return iterable<string, array{array{url: string, hash?: string, originalHash?: string, dimensions?: string, blurhash?: string, thumbnail?: string, image?: string, summary?: string, alt?: string, fallbacks?: list<string>}}>
     */
    public static function metadataHoldingANonUtf8String(): iterable
    {
        $url = 'https://cdn.example.com/abc.png';

        yield 'url' => [['url' => "https://cdn.example.com/\xff.png"]];
        yield 'hash' => [['url' => $url, 'hash' => "\xff"]];
        yield 'original hash' => [['url' => $url, 'originalHash' => "\xff"]];
        yield 'dimensions' => [['url' => $url, 'dimensions' => "\xff"]];
        yield 'blurhash' => [['url' => $url, 'blurhash' => "\xff"]];
        yield 'thumbnail' => [['url' => $url, 'thumbnail' => "\xff"]];
        yield 'image' => [['url' => $url, 'image' => "\xff"]];
        yield 'summary' => [['url' => $url, 'summary' => "\xff"]];
        yield 'alt' => [['url' => $url, 'alt' => "\xff"]];
        yield 'fallback' => [['url' => $url, 'fallbacks' => ["\xff"]]];
    }

    public function testIsBuiltOnlyThroughTheParserThatOwnsItsRules(): void
    {
        self::assertTrue(new ReflectionClass(FileMetadata::class)->getConstructor()?->isPrivate());
    }
}
