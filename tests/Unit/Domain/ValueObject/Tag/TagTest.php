<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function testCanCreateWithTypeAndValues(): void
    {
        $tag = new Tag(TagType::event(), ['event-id', 'relay-url']);

        $this->assertTrue($tag->getType()->equals(TagType::event()));
        $this->assertSame('event-id', $tag->getValue(0));
        $this->assertSame('relay-url', $tag->getValue(1));
        $this->assertSame(['event-id', 'relay-url'], $tag->getValues());
    }

    public function testAllowsEmptyValuesForFlagStyleTags(): void
    {
        $contentWarningType = TagType::fromString('content-warning');
        $tag = new Tag($contentWarningType, []);

        $this->assertTrue($tag->getType()->equals($contentWarningType));
        $this->assertSame([], $tag->getValues());
        $this->assertNull($tag->getValue(0));
    }

    public function testThrowsExceptionForNonStringValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All tag values must be strings');

        new Tag(TagType::event(), ['valid', 123]);
    }

    public function testGetValueReturnsNullForInvalidIndex(): void
    {
        $tag = new Tag(TagType::event(), ['event-id']);

        $this->assertNull($tag->getValue(1));
    }

    public function testHasValueWorksCorrectly(): void
    {
        $tag = new Tag(TagType::event(), ['event-id', 'relay-url']);

        $this->assertTrue($tag->hasValue('event-id'));
        $this->assertTrue($tag->hasValue('relay-url'));
        $this->assertFalse($tag->hasValue('not-present'));
    }

    public function testToArrayWorksCorrectly(): void
    {
        $tag = new Tag(TagType::event(), ['event-id', 'relay-url']);

        $expected = ['e', 'event-id', 'relay-url'];
        $this->assertSame($expected, $tag->toArray());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $tag1 = new Tag(TagType::event(), ['event-id']);
        $tag2 = new Tag(TagType::event(), ['event-id']);
        $tag3 = new Tag(TagType::pubkey(), ['event-id']);
        $tag4 = new Tag(TagType::event(), ['different-id']);

        $this->assertTrue($tag1->equals($tag2));
        $this->assertFalse($tag1->equals($tag3));
        $this->assertFalse($tag1->equals($tag4));
    }

    public function testStaticEventFactory(): void
    {
        $tag = Tag::event('event-id', 'wss://relay.example.com', 'root');

        $this->assertTrue($tag->getType()->equals(TagType::event()));
        $this->assertSame('event-id', $tag->getValue(0));
        $this->assertSame('wss://relay.example.com', $tag->getValue(1));
        $this->assertSame('root', $tag->getValue(2));
    }

    public function testStaticPubkeyFactory(): void
    {
        $tag = Tag::pubkey('pubkey-hex', 'wss://relay.example.com', 'alice');

        $this->assertTrue($tag->getType()->equals(TagType::pubkey()));
        $this->assertSame('pubkey-hex', $tag->getValue(0));
        $this->assertSame('wss://relay.example.com', $tag->getValue(1));
        $this->assertSame('alice', $tag->getValue(2));
    }

    public function testStaticHashtagFactory(): void
    {
        $tag = Tag::hashtag('nostr');

        $this->assertTrue($tag->getType()->equals(TagType::hashtag()));
        $this->assertSame('nostr', $tag->getValue(0));
    }

    public function testStaticIdentifierFactory(): void
    {
        $tag = Tag::identifier('my-id');

        $this->assertTrue($tag->getType()->equals(TagType::identifier()));
        $this->assertSame('my-id', $tag->getValue(0));
    }

    public function testFromArrayWorksCorrectly(): void
    {
        $data = ['e', 'event-id', 'relay-url'];
        $tag = Tag::fromArray($data);

        $this->assertTrue($tag->getType()->equals(TagType::event()));
        $this->assertSame('event-id', $tag->getValue(0));
        $this->assertSame('relay-url', $tag->getValue(1));
    }

    public function testFromArrayThrowsExceptionForEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag array cannot be empty');

        Tag::fromArray([]);
    }
}
