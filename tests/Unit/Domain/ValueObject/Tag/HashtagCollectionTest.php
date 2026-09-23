<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashtagCollectionTest extends TestCase
{
    public function testFromStringsLowercasesEachValue(): void
    {
        $this->assertSame(['nostr', 'bitcoin'], HashtagCollection::fromStrings(['NoStR', 'Bitcoin'])->toStrings());
    }

    public function testFromStringsDropsAValueThatIsNotAHashtag(): void
    {
        $this->assertSame(['kept'], HashtagCollection::fromStrings(['kept', '', 42, null, []])->toStrings());
    }

    public function testFromStringsOfANonIterableIsEmpty(): void
    {
        $this->assertCount(0, HashtagCollection::fromStrings('not a list'));
    }

    public function testTheConstructorRejectsAnElementOfAnotherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HashtagCollection(['a bare string']);
    }

    public function testTwoCollectionsDifferingOnlyInCaseAreEqual(): void
    {
        $this->assertTrue(HashtagCollection::fromStrings(['NOSTR'])->equals(HashtagCollection::fromStrings(['nostr'])));
    }

    public function testCollectionsOfDifferentLengthAreNotEqual(): void
    {
        $this->assertFalse(HashtagCollection::fromStrings(['a', 'bcd'])->equals(HashtagCollection::fromStrings(['a'])));
    }

    public function testACollectionBuiltFromHashtagsKeepsThem(): void
    {
        $this->assertSame(['abc'], new HashtagCollection([Hashtag::fromString('abc')])->toStrings());
    }

    public function testUniqueCollapsesCaseVariantsToOne(): void
    {
        $this->assertSame(['nostr'], HashtagCollection::fromStrings(['Nostr', 'nostr', 'NOSTR'])->unique()->toStrings());
    }

    public function testUniqueKeepsFirstOccurrenceOrder(): void
    {
        $this->assertSame(['b', 'a'], HashtagCollection::fromStrings(['b', 'a', 'B'])->unique()->toStrings());
    }
}
