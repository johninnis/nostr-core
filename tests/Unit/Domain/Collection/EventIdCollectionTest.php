<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\KeyedCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\IdentityKeyedInterface;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventIdCollectionTest extends TestCase
{
    public function testToHexesReturnsEachIdAsHex(): void
    {
        $this->assertSame(
            [str_repeat('a', 64), str_repeat('b', 64)],
            self::collection('a', 'b')->toHexes(),
        );
    }

    public function testContainsIsTrueForAPresentId(): void
    {
        $this->assertTrue(self::collection('a', 'b')->contains(self::id('a')));
    }

    public function testContainsIsFalseForAnAbsentId(): void
    {
        $this->assertFalse(self::collection('a')->contains(self::id('b')));
    }

    public function testUniqueRemovesDuplicates(): void
    {
        $this->assertSame(
            [str_repeat('a', 64)],
            self::collection('a', 'a')->unique()->toHexes(),
        );
    }

    public function testTryFromArrayRejectsTheWholeSetOnAnyInvalidElement(): void
    {
        $this->assertNull(EventIdCollection::tryFromArray([str_repeat('a', 64), 'not-hex']));
    }

    public function testTryFromArrayReturnsNullForANonArray(): void
    {
        $this->assertNull(EventIdCollection::tryFromArray('not-an-array'));
    }

    public function testTryFromArrayParsesEveryValidElement(): void
    {
        $collection = EventIdCollection::tryFromArray([str_repeat('a', 64), str_repeat('b', 64)])
            ?? throw new RuntimeException('Expected a valid collection');

        $this->assertSame([str_repeat('a', 64), str_repeat('b', 64)], $collection->toHexes());
    }

    public function testFromHexValuesDropsInvalidElementsAndKeepsTheRest(): void
    {
        $this->assertSame(
            [str_repeat('a', 64)],
            EventIdCollection::fromHexValues([str_repeat('a', 64), 'not-hex'])->toHexes(),
        );
    }

    private static function collection(string ...$chars): EventIdCollection
    {
        return new EventIdCollection(array_map(self::id(...), $chars));
    }

    private static function id(string $char): EventId
    {
        return EventId::tryFromHex(str_repeat($char, 64)) ?? throw new RuntimeException('Invalid test event id');
    }

    public function testDiffDropsTheIdsTheOtherCollectionHolds(): void
    {
        $kept = EventIdCollection::fromHexValues([str_repeat('a', 64), str_repeat('b', 64)])
            ->diff(EventIdCollection::fromHexValues([str_repeat('b', 64)]));

        $this->assertSame([str_repeat('a', 64)], $kept->toHexes());
    }

    public function testContainsRefusesAPublicKeyWithTheSameHexAsAHeldId(): void
    {
        $collection = self::asKeyed(EventIdCollection::fromHexValues([KeyMother::ALICE_PUBLIC_KEY_HEX]));

        $this->expectException(InvalidArgumentException::class);

        $collection->contains(KeyMother::alicePublicKey());
    }

    public function testIntersectRefusesAPublicKeyCollectionWithTheSameHexes(): void
    {
        $collection = self::asKeyed(EventIdCollection::fromHexValues([KeyMother::ALICE_PUBLIC_KEY_HEX]));

        $this->expectException(InvalidArgumentException::class);

        $collection->intersect(self::asKeyed(new PublicKeyCollection([KeyMother::alicePublicKey()])));
    }

    public function testDiffRefusesAPublicKeyCollectionWithTheSameHexes(): void
    {
        $collection = self::asKeyed(EventIdCollection::fromHexValues([KeyMother::ALICE_PUBLIC_KEY_HEX]));

        $this->expectException(InvalidArgumentException::class);

        $collection->diff(self::asKeyed(new PublicKeyCollection([KeyMother::alicePublicKey()])));
    }

    /**
     * @return KeyedCollection<IdentityKeyedInterface>
     */
    private static function asKeyed(object $collection): KeyedCollection
    {
        self::assertInstanceOf(KeyedCollection::class, $collection);

        return $collection;
    }
}
