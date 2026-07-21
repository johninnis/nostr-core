<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RelayUrlCollectionTest extends TestCase
{
    public function testMergeRejectsACollectionOfAnotherElementType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $eventIds = new EventIdCollection([EventId::tryFromHex(hash('sha256', 'seed')) ?? self::fail('invalid fixture event id')]);

        // @phpstan-ignore argument.type (the analyser rejects this too; the test pins the runtime guard)
        self::collection('wss://nos.lol')->merge($eventIds);
    }

    public function testMergeCombinesBothCollections(): void
    {
        $collection = self::collection('wss://nos.lol')->merge(self::collection('wss://relay.damus.io'));

        $this->assertSame(['wss://nos.lol', 'wss://relay.damus.io'], $collection->toStrings());
    }

    public function testMergePreservesTheReceiversRelaysFirst(): void
    {
        $collection = self::collection('wss://one.example', 'wss://two.example')->merge(self::collection('wss://three.example'));

        $this->assertSame(['wss://one.example', 'wss://two.example', 'wss://three.example'], $collection->toStrings());
    }

    public function testMergeWithAnEmptyCollectionKeepsTheReceiver(): void
    {
        $collection = self::collection('wss://nos.lol')->merge(new RelayUrlCollection());

        $this->assertSame(['wss://nos.lol'], $collection->toStrings());
    }

    public function testMergeLeavesTheReceiverUnchanged(): void
    {
        $collection = self::collection('wss://nos.lol');

        $collection->merge(self::collection('wss://relay.damus.io'));

        $this->assertSame(['wss://nos.lol'], $collection->toStrings());
    }

    public function testMergeKeepsDuplicatesUntilTheyAreDeduplicated(): void
    {
        $collection = self::collection('wss://nos.lol')->merge(self::collection('wss://nos.lol'));

        $this->assertCount(2, $collection);
        $this->assertSame(['wss://nos.lol'], $collection->unique()->toStrings());
    }

    private static function collection(string ...$urls): RelayUrlCollection
    {
        return RelayUrlCollection::fromStrings($urls);
    }
}
