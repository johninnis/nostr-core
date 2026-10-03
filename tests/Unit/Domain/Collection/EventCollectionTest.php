<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\EventCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventCollectionTest extends TestCase
{
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->keyPair = KeyMother::alice();
    }

    public function testCanCreateEmptyCollection(): void
    {
        $collection = new EventCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function testCanCreateCollectionWithEvents(): void
    {
        $event = $this->createEvent('Hello');
        $collection = new EventCollection([$event]);

        $this->assertFalse($collection->isEmpty());
        $this->assertSame(1, $collection->count());
    }

    public function testConstructorRejectsNonEventItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items must be '.Event::class.' instances');

        new EventCollection(['not-an-event']);
    }

    public function testAddReturnsNewCollectionWithEvent(): void
    {
        $collection = new EventCollection();
        $event = $this->createEvent('Hello');

        $newCollection = $collection->add($event);

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(1, $newCollection->count());
    }

    public function testRemoveReturnsNewCollectionWithoutEvent(): void
    {
        $event = $this->createEvent('Hello');
        $collection = new EventCollection([$event]);

        $newCollection = $collection->remove($event->getId());

        $this->assertSame(1, $collection->count());
        $this->assertTrue($newCollection->isEmpty());
    }

    public function testRemoveDoesNotAffectOtherEvents(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $newCollection = $collection->remove($event1->getId());

        $this->assertSame(1, $newCollection->count());
        $this->assertTrue($newCollection->contains($event2));
    }

    public function testContainsReturnsTrueWhenEventExists(): void
    {
        $event = $this->createEvent('Hello');
        $collection = new EventCollection([$event]);

        $this->assertTrue($collection->contains($event));
    }

    public function testContainsReturnsFalseWhenEventDoesNotExist(): void
    {
        $event1 = $this->createEvent('Hello');
        $event2 = $this->createEventAtTime('World', 1234567891);
        $collection = new EventCollection([$event1]);

        $this->assertFalse($collection->contains($event2));
    }

    public function testFilterByKindPredicateReturnsMatchingEvents(): void
    {
        $textNote = $this->createEvent('Text note');
        $metadata = $this->createEventWithKind(EventKind::fromInt(EventKind::METADATA), '{"name":"test"}');
        $collection = new EventCollection([$textNote, $metadata]);

        $filtered = $collection->filter(static fn (Event $event): bool => $event->getKind()->is(EventKind::TEXT_NOTE));

        $this->assertSame(1, $filtered->count());
        $first = $filtered->first();
        $this->assertNotNull($first);
        $this->assertSame('Text note', (string) $first->getContent());
    }

    public function testFilterReturnsEmptyCollectionWhenNoMatch(): void
    {
        $textNote = $this->createEvent('Text note');
        $collection = new EventCollection([$textNote]);

        $filtered = $collection->filter(static fn (Event $event): bool => $event->getKind()->is(EventKind::METADATA));

        $this->assertTrue($filtered->isEmpty());
    }

    public function testFilterByAuthorPredicateReturnsMatchingEvents(): void
    {
        $otherKeyPair = KeyMother::bob();
        $event1 = $this->createEvent('By original author');
        $event2 = EventMother::fromRumour(Rumour::draft(
            $otherKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('By other author'),
            new TagCollection(),
            Timestamp::fromInt(1234567890),
        ));
        $collection = new EventCollection([$event1, $event2]);

        $filtered = $collection->filter(fn (Event $event): bool => $event->getPubkey()->equals($this->keyPair->getPublicKey()));

        $this->assertSame(1, $filtered->count());
        $first = $filtered->first();
        $this->assertNotNull($first);
        $this->assertSame('By original author', (string) $first->getContent());
    }

    public function testFilterWithCallableReturnsMatchingEvents(): void
    {
        $event1 = $this->createEventAtTime('Early', 1000000000);
        $event2 = $this->createEventAtTime('Late', 2000000000);
        $collection = new EventCollection([$event1, $event2]);

        $threshold = Timestamp::fromInt(1500000000);
        $filtered = $collection->filter(
            static fn (Event $event) => $event->getCreatedAt()->isAfter($threshold)
        );

        $this->assertSame(1, $filtered->count());
        $first = $filtered->first();
        $this->assertNotNull($first);
        $this->assertSame('Late', (string) $first->getContent());
    }

    public function testSortByTimestampAscending(): void
    {
        $early = $this->createEventAtTime('Early', 1000000000);
        $late = $this->createEventAtTime('Late', 2000000000);
        $collection = new EventCollection([$late, $early]);

        $sorted = $collection->sortByTimestamp(true);

        $this->assertSame(['Early', 'Late'], array_map(static fn (Event $event): string => (string) $event->getContent(), $sorted->toArray()));
    }

    public function testSortByTimestampDescending(): void
    {
        $early = $this->createEventAtTime('Early', 1000000000);
        $late = $this->createEventAtTime('Late', 2000000000);
        $collection = new EventCollection([$early, $late]);

        $sorted = $collection->sortByTimestamp(false);

        $this->assertSame(['Late', 'Early'], array_map(static fn (Event $event): string => (string) $event->getContent(), $sorted->toArray()));
    }

    public function testSliceReturnsSubset(): void
    {
        $events = [];
        for ($i = 0; $i < 5; ++$i) {
            $events[] = $this->createEventAtTime("Event {$i}", 1234567890 + $i);
        }
        $collection = new EventCollection($events);

        $sliced = $collection->slice(1, 2);

        $this->assertSame(2, $sliced->count());
        $this->assertSame(['Event 1', 'Event 2'], array_map(static fn (Event $event): string => (string) $event->getContent(), $sliced->toArray()));
    }

    public function testSliceWithoutLengthReturnsFromOffset(): void
    {
        $events = [];
        for ($i = 0; $i < 3; ++$i) {
            $events[] = $this->createEventAtTime("Event {$i}", 1234567890 + $i);
        }
        $collection = new EventCollection($events);

        $sliced = $collection->slice(1);

        $this->assertSame(2, $sliced->count());
    }

    public function testFirstReturnsFirstEvent(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $first = $collection->first();
        $this->assertNotNull($first);
        $this->assertSame('First', (string) $first->getContent());
    }

    public function testFirstReturnsNullForEmptyCollection(): void
    {
        $collection = new EventCollection();

        $this->assertNull($collection->first());
    }

    public function testMergeCombinesTwoCollections(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection1 = new EventCollection([$event1]);
        $collection2 = new EventCollection([$event2]);

        $merged = $collection1->merge($collection2);

        $this->assertSame(2, $merged->count());
        $this->assertSame(1, $collection1->count());
        $this->assertSame(1, $collection2->count());
    }

    public function testUniqueRemovesDuplicateEvents(): void
    {
        $event = $this->createEvent('Hello');
        $collection = new EventCollection([$event, $event]);

        $unique = $collection->unique();

        $this->assertSame(1, $unique->count());
    }

    public function testUniquePreservesDistinctEvents(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $unique = $collection->unique();

        $this->assertSame(2, $unique->count());
    }

    public function testToArrayReturnsEventObjects(): void
    {
        $event = $this->createEvent('Hello');
        $collection = new EventCollection([$event]);

        $array = $collection->toArray();

        $this->assertCount(1, $array);
        $this->assertSame($event, $array[0]);
    }

    public function testToJsonArrayReturnsSerialisedEvents(): void
    {
        $event = $this->createEvent('Hello');
        $collection = new EventCollection([$event]);

        $jsonArray = $collection->toJsonArray();

        $this->assertCount(1, $jsonArray);
        $this->assertArrayHasKey('id', $jsonArray[0]);
        $this->assertArrayHasKey('pubkey', $jsonArray[0]);
        $this->assertArrayHasKey('content', $jsonArray[0]);
    }

    public function testIsEmptyReturnsTrueForEmptyCollection(): void
    {
        $collection = new EventCollection();

        $this->assertTrue($collection->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyCollection(): void
    {
        $collection = new EventCollection([$this->createEvent('Hello')]);

        $this->assertFalse($collection->isEmpty());
    }

    public function testCountReturnsNumberOfEvents(): void
    {
        $events = [
            $this->createEventAtTime('One', 1234567890),
            $this->createEventAtTime('Two', 1234567891),
            $this->createEventAtTime('Three', 1234567892),
        ];
        $collection = new EventCollection($events);

        $this->assertSame(3, $collection->count());
        $this->assertCount(3, $collection);
    }

    public function testGetIteratorAllowsForeachIteration(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $contents = [];
        $iterator = $collection->getIterator();
        foreach ($iterator as $event) {
            $contents[] = (string) $event->getContent();
        }

        $this->assertSame(['First', 'Second'], $contents);
    }

    public function testCollectionIsImmutable(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $original = new EventCollection([$event1]);

        $afterAdd = $original->add($event2);
        $afterFilter = $original->filter(static fn (Event $event): bool => $event->getKind()->is(EventKind::METADATA));
        $afterSort = $original->sortByTimestamp();

        $this->assertSame(1, $original->count());
        $this->assertSame(2, $afterAdd->count());
        $this->assertSame(0, $afterFilter->count());
        $this->assertSame(1, $afterSort->count());
    }

    public function testIntersectKeepsTheEventsTheOtherCollectionHolds(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);

        $shared = new EventCollection([$event1, $event2])->intersect(new EventCollection([$event2]));

        $this->assertSame([$event2], $shared->toArray());
    }

    private function createEvent(string $content): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString($content),
            new TagCollection(),
            Timestamp::fromInt(1234567890),
        ));
    }

    private function createEventAtTime(string $content, int $timestamp): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString($content),
            new TagCollection(),
            Timestamp::fromInt($timestamp),
        ));
    }

    private function createEventWithKind(EventKind $kind, string $content): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            $kind,
            EventContent::fromString($content),
            new TagCollection(),
            Timestamp::fromInt(1234567890),
        ));
    }
}
