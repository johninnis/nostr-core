<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\LongformMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class LongformMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollection::fromArray([
            ['d', 'my-article-slug'],
            ['title', 'My Article Title'],
            ['summary', 'A brief summary of the article'],
            ['image', 'https://example.com/image.jpg'],
            ['published_at', '1700000000'],
            ['t', 'nostr'],
            ['t', 'protocol'],
        ]);

        $metadata = LongformMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('my-article-slug', $metadata->getIdentifier());
        $this->assertSame('My Article Title', $metadata->getTitle());
        $this->assertSame('A brief summary of the article', $metadata->getSummary());
        $this->assertSame('https://example.com/image.jpg', $metadata->getImage());
        $this->assertNotNull($metadata->getPublishedAt());
        $this->assertSame(1700000000, $metadata->getPublishedAt()->toInt());
        $this->assertSame(['nostr', 'protocol'], $metadata->getTopics());
    }

    public function testFromTagCollectionWithOnlyIdentifier(): void
    {
        $tags = TagCollection::fromArray([
            ['d', 'minimal-article'],
        ]);

        $metadata = LongformMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('minimal-article', $metadata->getIdentifier());
        $this->assertNull($metadata->getTitle());
        $this->assertNull($metadata->getSummary());
        $this->assertNull($metadata->getImage());
        $this->assertNull($metadata->getPublishedAt());
        $this->assertSame([], $metadata->getTopics());
    }

    public function testReturnsNullWhenIdentifierMissing(): void
    {
        $tags = TagCollection::fromArray([
            ['title', 'No Identifier Article'],
            ['t', 'nostr'],
        ]);

        $this->assertNull(LongformMetadata::fromTagCollection($tags));
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = new LongformMetadata(
            'my-slug',
            'Title',
            'Summary',
            'https://example.com/img.jpg',
            Timestamp::fromInt(1700000000),
            ['nostr', 'dev']
        );

        $array = $original->toArray();
        $restored = LongformMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = new LongformMetadata('slug', null, null, null, null, []);

        $array = $original->toArray();
        $restored = LongformMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testEquals(): void
    {
        $a = new LongformMetadata('slug', 'Title', null, null, null, ['nostr']);
        $b = new LongformMetadata('slug', 'Title', null, null, null, ['nostr']);
        $c = new LongformMetadata('slug', 'Different', null, null, null, ['nostr']);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
