<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TagTypeTest extends TestCase
{
    public function testCanCreateFromString(): void
    {
        $tagType = TagType::fromString('e');

        $this->assertSame('e', (string) $tagType);
        $this->assertSame('e', (string) $tagType);
    }

    public function testThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag type cannot be empty');

        TagType::fromString('');
    }

    public function testStaticFactoryMethods(): void
    {
        $this->assertSame('e', (string) TagType::event());
        $this->assertSame('p', (string) TagType::pubkey());
        $this->assertSame('t', (string) TagType::hashtag());
        $this->assertSame('d', (string) TagType::identifier());
    }

    public function testNip22FactoryMethods(): void
    {
        $this->assertSame('E', (string) TagType::rootEvent());
        $this->assertSame('A', (string) TagType::rootAddress());
        $this->assertSame('I', (string) TagType::externalIdentity());
        $this->assertSame('K', (string) TagType::rootKind());
        $this->assertSame('k', (string) TagType::parentKind());
    }

    public function testUppercaseTagsDistinctFromLowercase(): void
    {
        $this->assertFalse(TagType::rootEvent()->equals(TagType::event()));
        $this->assertFalse(TagType::rootKind()->equals(TagType::parentKind()));
    }

    public function testEqualsWorksCorrectly(): void
    {
        $tagType1 = TagType::fromString('e');
        $tagType2 = TagType::fromString('e');
        $tagType3 = TagType::fromString('p');

        $this->assertTrue($tagType1->equals($tagType2));
        $this->assertFalse($tagType1->equals($tagType3));
    }

    public function testExpirationFactoryMethod(): void
    {
        $this->assertSame('expiration', (string) TagType::expiration());
    }

    public function testProtectedFactoryMethod(): void
    {
        $this->assertSame('-', (string) TagType::protected());
    }

    public function testCanCreateCustomTagTypes(): void
    {
        $customType = TagType::fromString('custom');

        $this->assertSame('custom', (string) $customType);
    }
}
