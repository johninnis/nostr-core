<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EventCoordinateCollectionTest extends TestCase
{
    private const string PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    public function testToJsonArrayWritesEachCoordinateWithItsRelayHint(): void
    {
        $collection = new EventCoordinateCollection([
            self::coordinate('30023:'.self::PUBKEY_HEX.':article', 'wss://relay.example.com'),
            self::coordinate('10000:'.self::PUBKEY_HEX.':'),
        ]);

        $this->assertSame([
            ['kind' => 30023, 'pubkey' => self::PUBKEY_HEX, 'identifier' => 'article', 'relay_hint' => 'wss://relay.example.com'],
            ['kind' => 10000, 'pubkey' => self::PUBKEY_HEX, 'identifier' => ''],
        ], $collection->toJsonArray());
    }

    public function testFromArraysRestoresWhatToJsonArrayWrote(): void
    {
        $written = new EventCoordinateCollection([self::coordinate('30023:'.self::PUBKEY_HEX.':article', 'wss://relay.example.com')])->toJsonArray();

        $this->assertSame($written, EventCoordinateCollection::fromArrays($written)->toJsonArray());
    }

    public function testFromArraysSkipsAnEntryThatIsNotACoordinate(): void
    {
        $collection = EventCoordinateCollection::fromArrays([
            ['kind' => 1, 'pubkey' => self::PUBKEY_HEX, 'identifier' => 'regular-kind'],
            'not-an-array',
            ['kind' => 30023, 'pubkey' => self::PUBKEY_HEX, 'identifier' => 'kept'],
        ]);

        $this->assertSame(['30023:'.self::PUBKEY_HEX.':kept'], array_map(strval(...), $collection->toArray()));
    }

    public function testFromArraysOfANonIterableIsEmpty(): void
    {
        $this->assertTrue(EventCoordinateCollection::fromArrays('not-iterable')->isEmpty());
    }

    public function testRefusesAnElementThatIsNotACoordinate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventCoordinateCollection([new stdClass()]);
    }

    private static function coordinate(string $value, ?string $relayHint = null): EventCoordinate
    {
        return EventCoordinate::tryFromString($value, $relayHint) ?? throw new InvalidArgumentException('Invalid test coordinate');
    }
}
