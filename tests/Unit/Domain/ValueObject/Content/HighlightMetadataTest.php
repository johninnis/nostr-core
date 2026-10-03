<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\HighlightMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\TestCase;

final class HighlightMetadataTest extends TestCase
{
    public function testFromTagCollectionWithAllFields(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['context', 'The surrounding paragraph text'],
            ['comment', 'This is an insightful passage'],
            ['r', 'https://example.com/article'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertSame('The surrounding paragraph text', $metadata->getContext());
        $this->assertSame('This is an insightful passage', $metadata->getComment());
        $this->assertSame('https://example.com/article', (string) $metadata->getSourceUrl());
    }

    public function testFromTagCollectionWithNoTags(): void
    {
        $tags = new TagCollection();

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertNull($metadata->getContext());
        $this->assertNull($metadata->getComment());
        $this->assertNull($metadata->getSourceUrl());
    }

    public function testAnEmptyContextOrCommentIsReadAsTheEmptyString(): void
    {
        $metadata = HighlightMetadata::fromTagCollection(TagCollectionMother::fromRaw([['context', ''], ['comment', '']]));

        $this->assertSame(['', ''], [$metadata->getContext(), $metadata->getComment()]);
    }

    public function testWssRelayUrlsIgnored(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'wss://relay.example.com'],
            ['r', 'wss://another-relay.com'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertNull($metadata->getSourceUrl());
    }

    public function testHttpUrlExtractedOverWssUrl(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'wss://relay.example.com'],
            ['r', 'https://example.com/article'],
            ['r', 'wss://another-relay.com'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertSame('https://example.com/article', (string) $metadata->getSourceUrl());
    }

    public function testHttpUrlExtracted(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'http://example.com/article'],
        ]);

        $metadata = HighlightMetadata::fromTagCollection($tags);

        $this->assertSame('http://example.com/article', (string) $metadata->getSourceUrl());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = HighlightMetadata::from('context text', 'my comment', HttpUrl::fromString('https://example.com/'));

        $array = $original->toArray();
        $restored = HighlightMetadata::tryFromArray($array) ?? $this->fail('Expected metadata');

        $this->assertTrue($original->equals($restored));
        $this->assertSame('context text', $array['context']);
        $this->assertSame('my comment', $array['comment']);
        $this->assertSame('https://example.com/', $array['source_url']);
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = HighlightMetadata::from(null, null, null);

        $array = $original->toArray();
        $restored = HighlightMetadata::tryFromArray($array) ?? $this->fail('Expected metadata');

        $this->assertTrue($original->equals($restored));
    }

    public function testEquals(): void
    {
        $a = HighlightMetadata::from('ctx', 'comment', HttpUrl::fromString('https://example.com/'));
        $b = HighlightMetadata::from('ctx', 'comment', HttpUrl::fromString('https://example.com/'));
        $c = HighlightMetadata::from('ctx', 'different', HttpUrl::fromString('https://example.com/'));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testSourceUrlIsTheOneMarkedSource(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'https://example.com/mentioned'],
            ['r', 'https://example.com/article', 'source'],
        ]);

        $this->assertSame('https://example.com/article', (string) HighlightMetadata::fromTagCollection($tags)->getSourceUrl());
    }

    public function testSourceUrlIgnoresAUrlTheCommentMentions(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'https://example.com/mentioned', 'mention'],
            ['r', 'https://example.com/article'],
        ]);

        $this->assertSame('https://example.com/article', (string) HighlightMetadata::fromTagCollection($tags)->getSourceUrl());
    }

    public function testSourceUrlIsNullWhenUnmarkedUrlsDisagree(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'https://example.com/one'],
            ['r', 'https://example.com/two'],
        ]);

        $this->assertNull(HighlightMetadata::fromTagCollection($tags)->getSourceUrl());
    }

    public function testContextIsNullWhenContextTagsDisagree(): void
    {
        $tags = TagCollectionMother::fromRaw([['context', 'one'], ['context', 'two']]);

        $this->assertNull(HighlightMetadata::fromTagCollection($tags)->getContext());
    }

    public function testCommentIsReadOnceWhenRepeated(): void
    {
        $tags = TagCollectionMother::fromRaw([['comment', 'same'], ['comment', 'same']]);

        $this->assertSame('same', HighlightMetadata::fromTagCollection($tags)->getComment());
    }

    public function testSourceUrlIsHeldInItsCanonicalForm(): void
    {
        $tags = TagCollectionMother::fromRaw([['r', 'HTTPS://Example.com:443']]);

        $this->assertSame('https://example.com/', (string) HighlightMetadata::fromTagCollection($tags)->getSourceUrl());
    }

    public function testSourceUrlsSpelledDifferentlyForOnePageAgree(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['r', 'https://example.com/article'],
            ['r', 'https://EXAMPLE.com/article'],
        ]);

        $this->assertSame('https://example.com/article', (string) HighlightMetadata::fromTagCollection($tags)->getSourceUrl());
    }

    public function testAnHttpPrefixThatDoesNotParseIsNeverTheSource(): void
    {
        $tags = TagCollectionMother::fromRaw([['r', 'https://']]);

        $this->assertNull(HighlightMetadata::fromTagCollection($tags)->getSourceUrl());
    }

    public function testTryFromRefusesAContextThatIsNotUtf8(): void
    {
        $this->assertNull(HighlightMetadata::tryFrom("\xff", null, null));
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArray(): void
    {
        $this->assertNull(HighlightMetadata::tryFromArray('context'));
    }

    public function testTryFromArrayRefusesACommentThatIsNotUtf8(): void
    {
        $this->assertNull(HighlightMetadata::tryFromArray(['comment' => "\xff"]));
    }
}
