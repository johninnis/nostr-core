<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\ChallengeCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChallengeCollectionTest extends TestCase
{
    public function testFromStringsParsesEachValue(): void
    {
        $this->assertSame(['one', 'two'], ChallengeCollection::fromStrings(['one', 'two'])->toStrings());
    }

    public function testFromStringsDropsAValueThatIsNotAChallenge(): void
    {
        $this->assertSame(['kept'], ChallengeCollection::fromStrings(['kept', '', 42, null, []])->toStrings());
    }

    public function testFromStringsOfANonIterableIsEmpty(): void
    {
        $this->assertCount(0, ChallengeCollection::fromStrings('not a list'));
    }

    public function testTheConstructorRejectsAnElementOfAnotherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChallengeCollection(['a bare string']);
    }

    public function testACollectionBuiltFromChallengesKeepsThem(): void
    {
        $collection = new ChallengeCollection([Challenge::fromString('abc')]);

        $this->assertSame(['abc'], $collection->toStrings());
    }
}
