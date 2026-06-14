<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use PHPUnit\Framework\TestCase;

final class FileMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollection::fromArray([
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

        $metadata = FileMetadata::fromTagCollection($tags);

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

    public function testFromTagCollectionReturnsNullWithoutUrl(): void
    {
        $tags = TagCollection::fromArray([['m', 'image/png']]);

        self::assertNull(FileMetadata::fromTagCollection($tags));
    }

    public function testFromTagCollectionIgnoresNonNumericSize(): void
    {
        $tags = TagCollection::fromArray([
            ['url', 'https://cdn.example.com/abc.png'],
            ['size', 'not-a-number'],
        ]);

        $metadata = FileMetadata::fromTagCollection($tags);

        self::assertNotNull($metadata);
        self::assertNull($metadata->getSize());
    }

    public function testToTagsRoundTrips(): void
    {
        $metadata = new FileMetadata(
            url: 'https://cdn.example.com/abc.png',
            mimeType: 'image/png',
            hash: str_repeat('a', 64),
            size: 4096,
            fallbacks: ['https://mirror.example.com/abc.png'],
        );

        $restored = FileMetadata::fromTagCollection($metadata->toTags());

        self::assertNotNull($restored);
        self::assertTrue($metadata->equals($restored));
    }

    public function testToTagsOmitsAbsentFields(): void
    {
        $metadata = new FileMetadata(url: 'https://cdn.example.com/abc.png');
        $tags = $metadata->toTags();

        self::assertCount(1, $tags);
        foreach ($tags as $tag) {
            self::assertInstanceOf(Tag::class, $tag);
            self::assertSame('url', (string) $tag->getType());
        }
    }

    public function testImetaTagRoundTrips(): void
    {
        $metadata = new FileMetadata(
            url: 'https://cdn.example.com/abc.png',
            mimeType: 'image/png',
            hash: str_repeat('a', 64),
            dimensions: '800x600',
        );

        $tag = $metadata->toImetaTag();

        self::assertSame('imeta', (string) $tag->getType());

        $restored = FileMetadata::fromImetaTag($tag);

        self::assertNotNull($restored);
        self::assertTrue($metadata->equals($restored));
    }

    public function testFromImetaTagRejectsNonImetaTag(): void
    {
        $tag = new Tag(TagType::fromString('e'), ['url https://cdn.example.com/abc.png']);

        self::assertNull(FileMetadata::fromImetaTag($tag));
    }
}
