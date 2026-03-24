<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Enum\CommentScope;
use Innis\Nostr\Core\Domain\ValueObject\Content\CommentMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use PHPUnit\Framework\TestCase;

final class CommentMetadataTest extends TestCase
{
    public function testEventScopeWhenRootEventTagPresent(): void
    {
        $tags = TagCollection::fromArray([
            ['K', '1'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['e', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', 'wss://relay.com', 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd'],
        ]);

        $metadata = CommentMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('1', $metadata->getRootKind());
        $this->assertSame('1111', $metadata->getParentKind());
        $this->assertSame(CommentScope::Event, $metadata->getRootScope());
    }

    public function testAddressScopeWhenNoRootEventTag(): void
    {
        $tags = TagCollection::fromArray([
            ['K', '30023'],
            ['k', '1111'],
            ['A', '30023:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:my-article', 'wss://relay.com'],
        ]);

        $metadata = CommentMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame(CommentScope::Address, $metadata->getRootScope());
    }

    public function testExternalScopeWhenNoEventOrAddressTag(): void
    {
        $tags = TagCollection::fromArray([
            ['K', 'web'],
            ['k', '1111'],
            ['I', 'https://example.com/article'],
        ]);

        $metadata = CommentMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame(CommentScope::External, $metadata->getRootScope());
    }

    public function testEventTakesPriorityOverAddress(): void
    {
        $tags = TagCollection::fromArray([
            ['K', '1'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['A', '30023:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb:slug'],
        ]);

        $metadata = CommentMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame(CommentScope::Event, $metadata->getRootScope());
    }

    public function testReturnsNullWhenRootKindMissing(): void
    {
        $tags = TagCollection::fromArray([
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::fromTagCollection($tags));
    }

    public function testReturnsNullWhenParentKindMissing(): void
    {
        $tags = TagCollection::fromArray([
            ['K', '1'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::fromTagCollection($tags));
    }

    public function testReturnsNullWhenScopeTagMissing(): void
    {
        $tags = TagCollection::fromArray([
            ['K', '1'],
            ['k', '1111'],
        ]);

        $this->assertNull(CommentMetadata::fromTagCollection($tags));
    }

    public function testNonNumericKindValues(): void
    {
        $tags = TagCollection::fromArray([
            ['K', 'web'],
            ['k', 'podcast:item:guid'],
            ['I', 'https://example.com/podcast/episode-1'],
        ]);

        $metadata = CommentMetadata::fromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('web', $metadata->getRootKind());
        $this->assertSame('podcast:item:guid', $metadata->getParentKind());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = new CommentMetadata('1', '1111', CommentScope::Event);

        $array = $original->toArray();
        $restored = CommentMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
        $this->assertSame('1', $array['root_kind']);
        $this->assertSame('1111', $array['parent_kind']);
        $this->assertSame('event', $array['root_scope']);
    }

    public function testEquals(): void
    {
        $a = new CommentMetadata('1', '1111', CommentScope::Event);
        $b = new CommentMetadata('1', '1111', CommentScope::Event);
        $c = new CommentMetadata('1', '1111', CommentScope::Address);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
