<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicKeyCollectionTest extends TestCase
{
    public function testToHexesReturnsEachKeyAsHex(): void
    {
        $this->assertSame(
            [str_repeat('a', 64), str_repeat('b', 64)],
            self::collection('a', 'b')->toHexes(),
        );
    }

    public function testContainsIsTrueForAPresentKey(): void
    {
        $this->assertTrue(self::collection('a', 'b')->contains(self::key('a')));
    }

    public function testContainsIsFalseForAnAbsentKey(): void
    {
        $this->assertFalse(self::collection('a')->contains(self::key('b')));
    }

    public function testIntersectKeepsOnlyKeysPresentInBoth(): void
    {
        $result = self::collection('a', 'b')->intersect(self::collection('b', 'c'));

        $this->assertSame([str_repeat('b', 64)], $result->toHexes());
    }

    public function testDiffKeepsKeysAbsentFromTheOther(): void
    {
        $result = self::collection('a', 'b')->diff(self::collection('b', 'c'));

        $this->assertSame([str_repeat('a', 64)], $result->toHexes());
    }

    private static function collection(string ...$chars): PublicKeyCollection
    {
        return new PublicKeyCollection(array_map(self::key(...), $chars));
    }

    private static function key(string $char): PublicKey
    {
        return PublicKey::fromHex(str_repeat($char, 64)) ?? throw new RuntimeException('Invalid test pubkey');
    }
}
