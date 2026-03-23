<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\HighlightMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use PHPUnit\Framework\TestCase;

final class HighlightMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollection::fromArray([
            ['context', 'The surrounding paragraph text'],
            ['comment', 'This is an insightful passage'],
            ['r', 'https://example.com/article'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertSame('The surrounding paragraph text', $metadata->getContext());
        $this->assertSame('This is an insightful passage', $metadata->getComment());
        $this->assertSame('https://example.com/article', $metadata->getSourceUrl());
    }

    public function testFromTagCollectionWithNoTags(): void
    {
        $tags = TagCollection::empty();

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertNull($metadata->getContext());
        $this->assertNull($metadata->getComment());
        $this->assertNull($metadata->getSourceUrl());
    }

    public function testWssRelayUrlsIgnored(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'wss://relay.example.com'],
            ['r', 'wss://another-relay.com'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertNull($metadata->getSourceUrl());
    }

    public function testHttpUrlExtractedOverWssUrl(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'wss://relay.example.com'],
            ['r', 'https://example.com/article'],
            ['r', 'wss://another-relay.com'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertSame('https://example.com/article', $metadata->getSourceUrl());
    }

    public function testHttpUrlExtracted(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'http://example.com/article'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertSame('http://example.com/article', $metadata->getSourceUrl());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = new HighlightMetadata('context text', 'my comment', 'https://example.com');

        $array = $original->toArray();
        $restored = HighlightMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
        $this->assertSame('context text', $array['context']);
        $this->assertSame('my comment', $array['comment']);
        $this->assertSame('https://example.com', $array['source_url']);
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = new HighlightMetadata(null, null, null);

        $array = $original->toArray();
        $restored = HighlightMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testEquals(): void
    {
        $a = new HighlightMetadata('ctx', 'comment', 'https://example.com');
        $b = new HighlightMetadata('ctx', 'comment', 'https://example.com');
        $c = new HighlightMetadata('ctx', 'different', 'https://example.com');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
