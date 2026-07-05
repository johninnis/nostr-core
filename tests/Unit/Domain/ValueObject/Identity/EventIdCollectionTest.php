<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
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
}
