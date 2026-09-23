<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
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

    public function testContainsFindsARelayRegardlessOfHowItWasWritten(): void
    {
        $this->assertTrue(self::collection('wss://nos.lol')->contains(self::relayUrl('wss://nos.lol/')));
    }

    public function testContainsIsFalseForARelayNotInTheCollection(): void
    {
        $this->assertFalse(self::collection('wss://nos.lol')->contains(self::relayUrl('wss://relay.damus.io')));
    }

    public function testDiffKeepsOnlyWhatTheOtherDoesNotHave(): void
    {
        $collection = self::collection('wss://nos.lol', 'wss://relay.damus.io')->diff(self::collection('wss://nos.lol'));

        $this->assertSame(['wss://relay.damus.io'], $collection->toStrings());
    }

    public function testDiffWithAnEmptyCollectionKeepsEverything(): void
    {
        $this->assertSame(['wss://nos.lol'], self::collection('wss://nos.lol')->diff(new RelayUrlCollection())->toStrings());
    }

    public function testIntersectKeepsOnlyWhatBothHave(): void
    {
        $collection = self::collection('wss://nos.lol', 'wss://relay.damus.io')->intersect(self::collection('wss://relay.damus.io', 'wss://other.example'));

        $this->assertSame(['wss://relay.damus.io'], $collection->toStrings());
    }

    private static function collection(string ...$urls): RelayUrlCollection
    {
        return RelayUrlCollection::fromStrings($urls);
    }

    private static function relayUrl(string $url): RelayUrl
    {
        return RelayUrl::tryFromString($url) ?? self::fail('invalid fixture relay url');
    }
}
