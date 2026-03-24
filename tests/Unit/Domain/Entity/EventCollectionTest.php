<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\EventCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventCollectionTest extends TestCase
{
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->keyPair = KeyPair::generate();
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
        $this->expectExceptionMessage('All items must be Event instances');

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
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$signedEvent]);

        $newCollection = $collection->remove($signedEvent->getId());

        $this->assertSame(1, $collection->count());
        $this->assertTrue($newCollection->isEmpty());
    }

    public function testRemoveDoesNotAffectOtherEvents(): void
    {
        $event1 = $this->createEvent('First')->sign($this->keyPair->getPrivateKey());
        $event2 = $this->createEventAtTime('Second', 1234567891)->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event1, $event2]);

        $newCollection = $collection->remove($event1->getId());

        $this->assertSame(1, $newCollection->count());
        $this->assertTrue($newCollection->contains($event2->getId()));
    }

    public function testContainsReturnsTrueWhenEventExists(): void
    {
        $event = $this->createEvent('Hello')->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event]);

        $this->assertTrue($collection->contains($event->getId()));
    }

    public function testContainsReturnsFalseWhenEventDoesNotExist(): void
    {
        $event1 = $this->createEvent('Hello')->sign($this->keyPair->getPrivateKey());
        $event2 = $this->createEventAtTime('World', 1234567891)->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event1]);

        $this->assertFalse($collection->contains($event2->getId()));
    }

    public function testFilterByKindReturnsMatchingEvents(): void
    {
        $textNote = $this->createEvent('Text note');
        $metadata = $this->createEventWithKind(EventKind::metadata(), '{"name":"test"}');
        $collection = new EventCollection([$textNote, $metadata]);

        $filtered = $collection->filterByKind(EventKind::textNote());

        $this->assertSame(1, $filtered->count());
        $first = $filtered->first();
        $this->assertNotNull($first);
        $this->assertSame('Text note', (string) $first->getContent());
    }

    public function testFilterByKindReturnsEmptyCollectionWhenNoMatch(): void
    {
        $textNote = $this->createEvent('Text note');
        $collection = new EventCollection([$textNote]);

        $filtered = $collection->filterByKind(EventKind::metadata());

        $this->assertTrue($filtered->isEmpty());
    }

    public function testFilterByAuthorReturnsMatchingEvents(): void
    {
        $otherKeyPair = KeyPair::generate();
        $event1 = $this->createEvent('By original author');
        $event2 = new Event(
            $otherKeyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('By other author')
        );
        $collection = new EventCollection([$event1, $event2]);

        $filtered = $collection->filterByAuthor($this->keyPair->getPublicKey());

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

    public function testMapTransformsAllEvents(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $mapped = $collection->map(
            static fn (Event $event) => new Event(
                $event->getPubkey(),
                $event->getCreatedAt(),
                $event->getKind(),
                $event->getTags(),
                EventContent::fromString('Mapped: '.(string) $event->getContent())
            )
        );

        $this->assertSame(2, $mapped->count());
        $first = $mapped->first();
        $last = $mapped->last();
        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertSame('Mapped: First', (string) $first->getContent());
        $this->assertSame('Mapped: Second', (string) $last->getContent());
    }

    public function testReduceAggregatesValues(): void
    {
        $event1 = $this->createEvent('Hello');
        $event2 = $this->createEventAtTime('World', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $result = $collection->reduce(
            static fn (string $carry, Event $event) => $carry.' '.(string) $event->getContent(),
            ''
        );

        $this->assertSame(' Hello World', $result);
    }

    public function testReduceWithEmptyCollectionReturnsInitial(): void
    {
        $collection = new EventCollection();

        $result = $collection->reduce(
            static fn (int $carry, Event $event) => $carry + 1,
            0
        );

        $this->assertSame(0, $result);
    }

    public function testSortByTimestampAscending(): void
    {
        $early = $this->createEventAtTime('Early', 1000000000);
        $late = $this->createEventAtTime('Late', 2000000000);
        $collection = new EventCollection([$late, $early]);

        $sorted = $collection->sortByTimestamp(true);

        $first = $sorted->first();
        $last = $sorted->last();
        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertSame('Early', (string) $first->getContent());
        $this->assertSame('Late', (string) $last->getContent());
    }

    public function testSortByTimestampDescending(): void
    {
        $early = $this->createEventAtTime('Early', 1000000000);
        $late = $this->createEventAtTime('Late', 2000000000);
        $collection = new EventCollection([$early, $late]);

        $sorted = $collection->sortByTimestamp(false);

        $first = $sorted->first();
        $last = $sorted->last();
        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertSame('Late', (string) $first->getContent());
        $this->assertSame('Early', (string) $last->getContent());
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
        $first = $sliced->first();
        $last = $sliced->last();
        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertSame('Event 1', (string) $first->getContent());
        $this->assertSame('Event 2', (string) $last->getContent());
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

    public function testLastReturnsLastEvent(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $collection = new EventCollection([$event1, $event2]);

        $last = $collection->last();
        $this->assertNotNull($last);
        $this->assertSame('Second', (string) $last->getContent());
    }

    public function testLastReturnsNullForEmptyCollection(): void
    {
        $collection = new EventCollection();

        $this->assertNull($collection->last());
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
        $event = $this->createEvent('Hello')->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event, $event]);

        $unique = $collection->unique();

        $this->assertSame(1, $unique->count());
    }

    public function testUniquePreservesDistinctEvents(): void
    {
        $event1 = $this->createEvent('First')->sign($this->keyPair->getPrivateKey());
        $event2 = $this->createEventAtTime('Second', 1234567891)->sign($this->keyPair->getPrivateKey());
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
        $this->assertInstanceOf(Event::class, $array[0]);
    }

    public function testToJsonArrayReturnsSerialisedEvents(): void
    {
        $event = $this->createEvent('Hello')->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event]);

        $jsonArray = $collection->toJsonArray();

        $this->assertCount(1, $jsonArray);
        $this->assertIsArray($jsonArray[0]);
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
            $this->assertInstanceOf(Event::class, $event);
            $contents[] = (string) $event->getContent();
        }

        $this->assertSame(['First', 'Second'], $contents);
    }

    public function testJsonSerializeReturnsSerialisedEvents(): void
    {
        $event = $this->createEvent('Hello')->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event]);

        $serialised = $collection->jsonSerialize();

        $this->assertSame($collection->toJsonArray(), $serialised);
    }

    public function testJsonEncodeProducesValidJson(): void
    {
        $event = $this->createEvent('Hello')->sign($this->keyPair->getPrivateKey());
        $collection = new EventCollection([$event]);

        $json = json_encode($collection);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
    }

    public function testCollectionIsImmutable(): void
    {
        $event1 = $this->createEvent('First');
        $event2 = $this->createEventAtTime('Second', 1234567891);
        $original = new EventCollection([$event1]);

        $afterAdd = $original->add($event2);
        $afterFilter = $original->filterByKind(EventKind::metadata());
        $afterSort = $original->sortByTimestamp();

        $this->assertSame(1, $original->count());
        $this->assertSame(2, $afterAdd->count());
        $this->assertSame(0, $afterFilter->count());
        $this->assertSame(1, $afterSort->count());
    }

    private function createEvent(string $content): Event
    {
        return new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString($content)
        );
    }

    private function createEventAtTime(string $content, int $timestamp): Event
    {
        return new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt($timestamp),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString($content)
        );
    }

    private function createEventWithKind(EventKind $kind, string $content): Event
    {
        return new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            $kind,
            TagCollection::empty(),
            EventContent::fromString($content)
        );
    }
}
