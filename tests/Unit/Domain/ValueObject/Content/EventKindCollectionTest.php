<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use PHPUnit\Framework\TestCase;

final class EventKindCollectionTest extends TestCase
{
    public function testToIntsReturnsEachKindAsInt(): void
    {
        $this->assertSame([1, 7], self::collection(1, 7)->toInts());
    }

    public function testContainsIsTrueForAPresentKind(): void
    {
        $this->assertTrue(self::collection(1, 7)->contains(EventKind::fromInt(1)));
    }

    public function testContainsIsFalseForAnAbsentKind(): void
    {
        $this->assertFalse(self::collection(1)->contains(EventKind::fromInt(7)));
    }

    public function testIntersectKeepsOnlyKindsPresentInBoth(): void
    {
        $result = self::collection(1, 7)->intersect(self::collection(7, 30023));

        $this->assertSame([7], $result->toInts());
    }

    public function testDiffKeepsKindsAbsentFromTheOther(): void
    {
        $result = self::collection(1, 7)->diff(self::collection(7, 30023));

        $this->assertSame([1], $result->toInts());
    }

    private static function collection(int ...$kinds): EventKindCollection
    {
        return new EventKindCollection(array_map(EventKind::fromInt(...), $kinds));
    }
}
