<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\LiveEventMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use PHPUnit\Framework\TestCase;

final class LiveEventMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollection::fromArray([
            ['d', 'my-live-stream'],
            ['title', 'Live Coding Session'],
            ['summary', 'Building a Nostr client from scratch'],
            ['image', 'https://example.com/thumbnail.jpg'],
            ['status', 'live'],
            ['streaming', 'https://stream.example.com/live.m3u8'],
        ]);

        $metadata = LiveEventMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('my-live-stream', $metadata->getIdentifier());
        $this->assertSame('Live Coding Session', $metadata->getTitle());
        $this->assertSame('Building a Nostr client from scratch', $metadata->getSummary());
        $this->assertSame('https://example.com/thumbnail.jpg', $metadata->getImage());
        $this->assertSame('live', $metadata->getStatus());
        $this->assertSame('https://stream.example.com/live.m3u8', $metadata->getStreaming());
    }

    public function testFromTagCollectionWithOnlyIdentifier(): void
    {
        $tags = TagCollection::fromArray([
            ['d', 'minimal-stream'],
        ]);

        $metadata = LiveEventMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('minimal-stream', $metadata->getIdentifier());
        $this->assertNull($metadata->getTitle());
        $this->assertNull($metadata->getSummary());
        $this->assertNull($metadata->getImage());
        $this->assertNull($metadata->getStatus());
        $this->assertNull($metadata->getStreaming());
    }

    public function testReturnsNullWhenIdentifierMissing(): void
    {
        $tags = TagCollection::fromArray([
            ['title', 'No Identifier Stream'],
            ['status', 'live'],
        ]);

        $this->assertNull(LiveEventMetadata::fromTagCollection($tags));
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = new LiveEventMetadata(
            'my-stream',
            'Title',
            'Summary',
            'https://example.com/img.jpg',
            'live',
            'https://stream.example.com/live.m3u8'
        );

        $array = $original->toArray();
        $restored = LiveEventMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = new LiveEventMetadata('slug', null, null, null, null, null);

        $array = $original->toArray();
        $restored = LiveEventMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testEquals(): void
    {
        $a = new LiveEventMetadata('slug', 'Title', null, null, 'live', null);
        $b = new LiveEventMetadata('slug', 'Title', null, null, 'live', null);
        $c = new LiveEventMetadata('slug', 'Title', null, null, 'ended', null);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
