<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Enum\CommentScope;
use Innis\Nostr\Core\Domain\ValueObject\Content\CommentMetadata;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\TestCase;

final class CommentMetadataTest extends TestCase
{
    public function testEventScopeWhenRootEventTagPresent(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['e', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', 'wss://relay.com', 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd'],
        ]);

        $metadata = CommentMetadata::tryFromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('1', $metadata->getRootKind());
        $this->assertSame('1111', $metadata->getParentKind());
        $this->assertSame(CommentScope::Event, $metadata->getRootScope());
    }

    public function testAddressScopeWhenNoRootEventTag(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '30023'],
            ['k', '1111'],
            ['A', '30023:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:my-article', 'wss://relay.com'],
        ]);

        $metadata = CommentMetadata::tryFromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame(CommentScope::Address, $metadata->getRootScope());
    }

    public function testExternalScopeWhenNoEventOrAddressTag(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', 'web'],
            ['k', '1111'],
            ['I', 'https://example.com/article'],
        ]);

        $metadata = CommentMetadata::tryFromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame(CommentScope::External, $metadata->getRootScope());
    }

    public function testAnExternalRootWithAnEmptyKHasNoScope(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', ''],
            ['k', '1111'],
            ['I', 'https://example.com/article'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testAddressTakesPriorityOverEvent(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '30023'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['A', '30023:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb:slug'],
        ]);

        $this->assertSame(CommentScope::Address, CommentMetadata::tryFromTagCollection($tags)?->getRootScope());
    }

    public function testEventTakesPriorityOverExternalContent(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['k', '1111'],
            ['I', 'isbn:9780765382030'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertSame(CommentScope::Event, CommentMetadata::tryFromTagCollection($tags)?->getRootScope());
    }

    public function testAddressTagsThatDisagreeAreNoClaimSoTheEventIsRead(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '30023'],
            ['k', '1111'],
            ['A', '30023:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb:one'],
            ['A', '30023:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb:two'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertSame(CommentScope::Event, CommentMetadata::tryFromTagCollection($tags)?->getRootScope());
    }

    public function testEventTagsThatDisagreeAndNothingElseNameNoScope(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['E', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testReturnsNullWhenRootKindMissing(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testReturnsNullWhenParentKindMissing(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testReturnsNullWhenScopeTagMissing(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['k', '1111'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testNonNumericKindValues(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', 'web'],
            ['k', 'podcast:item:guid'],
            ['I', 'https://example.com/podcast/episode-1'],
        ]);

        $metadata = CommentMetadata::tryFromTagCollection($tags);

        $this->assertNotNull($metadata);
        $this->assertSame('web', $metadata->getRootKind());
        $this->assertSame('podcast:item:guid', $metadata->getParentKind());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = CommentMetadata::from('1', '1111', CommentScope::Event);

        $array = $original->toArray();
        $restored = CommentMetadata::tryFromArray($array);

        $this->assertNotNull($restored);
        $this->assertTrue($original->equals($restored));
        $this->assertSame('1', $array['root_kind']);
        $this->assertSame('1111', $array['parent_kind']);
        $this->assertSame('event', $array['root_scope']);
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArray(): void
    {
        $this->assertNull(CommentMetadata::tryFromArray('1111'));
    }

    public function testTryFromArrayReturnsNullWhenFieldMissing(): void
    {
        $this->assertNull(CommentMetadata::tryFromArray(['root_kind' => '1', 'parent_kind' => '1111']));
    }

    public function testTryFromArrayReturnsNullWhenScopeUnrecognised(): void
    {
        $this->assertNull(CommentMetadata::tryFromArray([
            'root_kind' => '1',
            'parent_kind' => '1111',
            'root_scope' => 'bogus',
        ]));
    }

    public function testEquals(): void
    {
        $a = CommentMetadata::from('1', '1111', CommentScope::Event);
        $b = CommentMetadata::from('1', '1111', CommentScope::Event);
        $c = CommentMetadata::from('1', '1111', CommentScope::Address);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testReturnsNullWhenRootKindTagsDisagree(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['K', '30023'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testReturnsNullWhenParentKindTagsDisagree(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['k', '1'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testReadsARepeatedKindTagAsOneClaim(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['K', '1'],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertSame('1', CommentMetadata::tryFromTagCollection($tags)?->getRootKind());
    }

    public function testTryFromRefusesARootKindThatIsNotUtf8(): void
    {
        $this->assertNull(CommentMetadata::tryFrom("\xff", '1111', CommentScope::Event));
    }

    public function testTryFromArrayRefusesAParentKindThatIsNotUtf8(): void
    {
        $this->assertNull(CommentMetadata::tryFromArray(['root_kind' => '1', 'parent_kind' => "\xff", 'root_scope' => 'event']));
    }

    public function testAnEventRootWithAnEmptyKHasNoMetadata(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', ''],
            ['k', '1111'],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testAnEmptyParentKindHasNoMetadata(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['K', '1'],
            ['k', ''],
            ['E', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $this->assertNull(CommentMetadata::tryFromTagCollection($tags));
    }

    public function testTryFromRefusesAnEmptyRootKind(): void
    {
        $this->assertNull(CommentMetadata::tryFrom('', '1111', CommentScope::Event));
    }

    public function testTryFromArrayRefusesAnEmptyParentKind(): void
    {
        $this->assertNull(CommentMetadata::tryFromArray(['root_kind' => '1', 'parent_kind' => '', 'root_scope' => 'event']));
    }
}
