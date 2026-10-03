<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\LiveEventMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\TestCase;

final class LiveEventMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['d', 'my-live-stream'],
            ['title', 'Live Coding Session'],
            ['summary', 'Building a Nostr client from scratch'],
            ['image', 'https://example.com/thumbnail.jpg'],
            ['status', 'live'],
            ['streaming', 'https://stream.example.com/live.m3u8'],
        ]);

        $metadata = LiveEventMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame('my-live-stream', $metadata->getIdentifier());
        $this->assertSame('Live Coding Session', $metadata->getTitle());
        $this->assertSame('Building a Nostr client from scratch', $metadata->getSummary());
        $this->assertSame('https://example.com/thumbnail.jpg', (string) $metadata->getImage());
        $this->assertSame('live', $metadata->getStatus());
        $this->assertSame('https://stream.example.com/live.m3u8', (string) $metadata->getStreaming());
    }

    public function testFromTagCollectionWithOnlyIdentifier(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['d', 'minimal-stream'],
        ]);

        $metadata = LiveEventMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame('minimal-stream', $metadata->getIdentifier());
        $this->assertNull($metadata->getTitle());
        $this->assertNull($metadata->getSummary());
        $this->assertNull($metadata->getImage());
        $this->assertNull($metadata->getStatus());
        $this->assertNull($metadata->getStreaming());
    }

    public function testTreatsAMissingIdentifierAsEmpty(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['title', 'No Identifier Stream'],
            ['status', 'live'],
        ]);

        $this->assertSame('', (LiveEventMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata'))->getIdentifier());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = LiveEventMetadata::from(
            'my-stream',
            'Title',
            'Summary',
            HttpUrl::fromString('https://example.com/img.jpg'),
            'live',
            HttpUrl::fromString('https://stream.example.com/live.m3u8'),
        );

        $array = $original->toArray();
        $restored = LiveEventMetadata::tryFromArray($array);

        $this->assertNotNull($restored);
        $this->assertTrue($original->equals($restored));
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = LiveEventMetadata::from('slug', null, null, null, null, null);

        $array = $original->toArray();
        $restored = LiveEventMetadata::tryFromArray($array);

        $this->assertNotNull($restored);
        $this->assertTrue($original->equals($restored));
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArray(): void
    {
        $this->assertNull(LiveEventMetadata::tryFromArray('stream'));
    }

    public function testTryFromArrayReturnsNullWhenIdentifierMissing(): void
    {
        $this->assertNull(LiveEventMetadata::tryFromArray(['title' => 'No identifier']));
    }

    public function testTryFromArrayReturnsNullWhenIdentifierNotString(): void
    {
        $this->assertNull(LiveEventMetadata::tryFromArray(['identifier' => 123]));
    }

    public function testEquals(): void
    {
        $a = LiveEventMetadata::from('slug', 'Title', null, null, 'live', null);
        $b = LiveEventMetadata::from('slug', 'Title', null, null, 'live', null);
        $c = LiveEventMetadata::from('slug', 'Title', null, null, 'ended', null);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testTryFromTagCollectionIsNullWhenDTagsDisagree(): void
    {
        $tags = new TagCollection([Tag::identifier('first'), Tag::identifier('second')]);

        $this->assertNull(LiveEventMetadata::tryFromTagCollection($tags));
    }

    public function testTryFromTagCollectionReadsARepeatedDTagAsOneIdentifier(): void
    {
        $tags = new TagCollection([Tag::identifier('slug'), Tag::identifier('slug')]);

        $this->assertSame('slug', LiveEventMetadata::tryFromTagCollection($tags)?->getIdentifier());
    }

    public function testTryFromTagCollectionReadsNoTitleFromTitleTagsThatDisagree(): void
    {
        $tags = new TagCollection([Tag::identifier('slug'), Tag::fromArray(['title', 'One']), Tag::fromArray(['title', 'Two'])]);

        $this->assertNull(LiveEventMetadata::tryFromTagCollection($tags)?->getTitle());
    }

    public function testTryFromTagCollectionReadsARepeatedTitleAsOneClaim(): void
    {
        $tags = new TagCollection([Tag::identifier('slug'), Tag::fromArray(['title', 'One']), Tag::fromArray(['title', 'One'])]);

        $this->assertSame('One', LiveEventMetadata::tryFromTagCollection($tags)?->getTitle());
    }

    public function testTryFromRefusesATitleThatIsNotUtf8(): void
    {
        $this->assertNull(LiveEventMetadata::tryFrom('slug', "\xff", null, null, null, null));
    }

    public function testTryFromArrayRefusesAStatusThatIsNotUtf8(): void
    {
        $this->assertNull(LiveEventMetadata::tryFromArray(['identifier' => 'slug', 'status' => "\xff"]));
    }

    public function testTryFromTagCollectionHoldsTheImageAndStreamingUrlsInCanonicalForm(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['d', 'slug'],
            ['image', 'HTTPS://Example.com:443/thumb.jpg'],
            ['streaming', 'https://Stream.Example.com/live.m3u8'],
        ]);

        $metadata = LiveEventMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame(
            ['https://example.com/thumb.jpg', 'https://stream.example.com/live.m3u8'],
            [(string) $metadata->getImage(), (string) $metadata->getStreaming()],
        );
    }

    public function testTryFromTagCollectionDropsAnImageOrStreamingValueThatIsNotAWebUrl(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['d', 'slug'],
            ['image', 'thumbnail.jpg'],
            ['streaming', 'rtmp://stream.example.com/live'],
        ]);

        $metadata = LiveEventMetadata::tryFromTagCollection($tags) ?? $this->fail('Expected metadata');

        $this->assertSame([null, null], [$metadata->getImage(), $metadata->getStreaming()]);
    }

    public function testTryFromArrayDropsAStoredImageThatIsNotAWebUrl(): void
    {
        $this->assertNull(LiveEventMetadata::tryFromArray(['identifier' => 'slug', 'image' => 'not a url'])?->getImage());
    }

    public function testEqualityComparesTheStreamingUrl(): void
    {
        $a = LiveEventMetadata::from('slug', null, null, null, null, HttpUrl::fromString('https://a.example/live'));
        $b = LiveEventMetadata::from('slug', null, null, null, null, HttpUrl::fromString('https://b.example/live'));

        $this->assertFalse($a->equals($b));
    }
}
