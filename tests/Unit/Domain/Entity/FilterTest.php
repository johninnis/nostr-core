<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FilterTest extends TestCase
{
    public function testCanCreateFilter(): void
    {
        $filter = new Filter(
            ids: [str_repeat('a', 64)],
            authors: [str_repeat('b', 64)],
            kinds: [1, 2],
            tags: ['t' => ['nostr']],
            since: Timestamp::fromInt(1234567890),
            until: Timestamp::fromInt(1234567900),
            limit: 10
        );

        $this->assertIdHexes([str_repeat('a', 64)], $filter->getIds());
        $this->assertAuthorHexes([str_repeat('b', 64)], $filter->getAuthors());
        $this->assertKinds([1, 2], $filter->getKinds());
        $this->assertSame(['t' => ['nostr']], $filter->getTags());
        $since = $filter->getSince();
        $until = $filter->getUntil();
        $this->assertNotNull($since);
        $this->assertNotNull($until);
        $this->assertSame(1234567890, $since->toInt());
        $this->assertSame(1234567900, $until->toInt());
        $this->assertSame(10, $filter->getLimit());
    }

    public function testThrowsExceptionForInvalidLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 5000');

        new Filter(limit: 0);
    }

    public function testThrowsExceptionForInvalidTimeRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Since timestamp cannot be after until timestamp');

        new Filter(
            since: Timestamp::fromInt(1234567900),
            until: Timestamp::fromInt(1234567890)
        );
    }

    public function testMatchesEventById(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );
        $signedEvent = $event->sign($keyPair, FakeSignatureService::accepting());

        $filter = new Filter(ids: [$signedEvent->getId()->toHex()]);

        $this->assertTrue($filter->matches($signedEvent));
    }

    public function testMatchesEventByAuthor(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(authors: [$keyPair->getPublicKey()->toHex()]);

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventByKind(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(kinds: [1]);

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventByTag(): void
    {
        $keyPair = KeyMother::alice();
        $tags = new TagCollection([Tag::hashtag('nostr')]);
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            $tags,
            EventContent::fromString('test')
        );

        $filter = new Filter(tags: ['t' => ['nostr']]);

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventWithMultipleTagTypesRequiresAll(): void
    {
        $keyPair = KeyMother::alice();
        $pubkeyHex = str_repeat('a', 64);
        $tags = new TagCollection([
            Tag::hashtag('nostr'),
            Tag::pubkey($pubkeyHex),
        ]);
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            $tags,
            EventContent::fromString('test')
        );

        $filterBothMatch = new Filter(tags: ['t' => ['nostr'], 'p' => [$pubkeyHex]]);
        $this->assertTrue($filterBothMatch->matches($event));

        $filterOnlyPMissing = new Filter(tags: ['t' => ['nostr'], 'p' => [str_repeat('b', 64)]]);
        $this->assertFalse($filterOnlyPMissing->matches($event));

        $filterOnlyTMissing = new Filter(tags: ['t' => ['bitcoin'], 'p' => [$pubkeyHex]]);
        $this->assertFalse($filterOnlyTMissing->matches($event));
    }

    public function testTagFilterMatchesOnlyTheTagValuePosition(): void
    {
        $pubkey = PublicKey::fromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test public key');
        $referencedId = str_repeat('c', 64);
        $tags = new TagCollection([Tag::event($referencedId, 'wss://relay.example', 'reply')]);
        $event = new Event(
            $pubkey,
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            $tags,
            EventContent::fromString('test')
        );

        $this->assertTrue((new Filter(tags: ['e' => [$referencedId]]))->matches($event));
        $this->assertFalse((new Filter(tags: ['e' => ['wss://relay.example']]))->matches($event));
        $this->assertFalse((new Filter(tags: ['e' => ['reply']]))->matches($event));
    }

    public function testMatchesEventWithMultipleValuesInSameTagType(): void
    {
        $keyPair = KeyMother::alice();
        $tags = new TagCollection([Tag::hashtag('nostr')]);
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            $tags,
            EventContent::fromString('test')
        );

        $filter = new Filter(tags: ['t' => ['nostr', 'bitcoin']]);
        $this->assertTrue($filter->matches($event));
    }

    public function testDoesNotMatchWhenNoTagsMatchFilter(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(tags: ['t' => ['nostr']]);
        $this->assertFalse($filter->matches($event));
    }

    public function testDoesNotMatchWhenCriteriaNotMet(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(kinds: [2]); // Different kind

        $this->assertFalse($filter->matches($event));
    }

    public function testCanConvertToArray(): void
    {
        $filter = new Filter(
            ids: ['event-id'],
            authors: ['author-pubkey'],
            kinds: [1],
            tags: ['t' => ['nostr']],
            since: Timestamp::fromInt(1234567890),
            until: Timestamp::fromInt(1234567900),
            limit: 10
        );

        $array = $filter->toArray();

        $this->assertArrayHasKey('ids', $array);
        $this->assertArrayHasKey('authors', $array);
        $this->assertArrayHasKey('kinds', $array);
        $this->assertArrayHasKey('#t', $array);
        $this->assertArrayHasKey('since', $array);
        $this->assertArrayHasKey('until', $array);
        $this->assertArrayHasKey('limit', $array);
    }

    public function testCanCreateFromArray(): void
    {
        $data = [
            'ids' => [str_repeat('a', 64)],
            'authors' => [str_repeat('b', 64)],
            'kinds' => [1],
            '#t' => ['nostr'],
            'since' => 1234567890,
            'until' => 1234567900,
            'limit' => 10,
        ];

        $filter = Filter::fromArray($data);

        $this->assertNotNull($filter);
        $this->assertIdHexes([str_repeat('a', 64)], $filter->getIds());
        $this->assertAuthorHexes([str_repeat('b', 64)], $filter->getAuthors());
        $this->assertKinds([1], $filter->getKinds());
        $this->assertSame(['t' => ['nostr']], $filter->getTags());
        $since = $filter->getSince();
        $until = $filter->getUntil();
        $this->assertNotNull($since);
        $this->assertNotNull($until);
        $this->assertSame(1234567890, $since->toInt());
        $this->assertSame(1234567900, $until->toInt());
        $this->assertSame(10, $filter->getLimit());
    }

    public function testHasIdsReturnsTrueWhenIdsAreSet(): void
    {
        $filter = new Filter(ids: ['event-id']);

        $this->assertTrue($filter->hasIds());
    }

    public function testHasIdsReturnsFalseWhenIdsAreNull(): void
    {
        $filter = new Filter();

        $this->assertFalse($filter->hasIds());
    }

    public function testHasAuthorsReturnsTrueWhenAuthorsAreSet(): void
    {
        $filter = new Filter(authors: ['author-pubkey']);

        $this->assertTrue($filter->hasAuthors());
    }

    public function testHasAuthorsReturnsFalseWhenAuthorsAreNull(): void
    {
        $filter = new Filter();

        $this->assertFalse($filter->hasAuthors());
    }

    public function testHasKindsReturnsTrueWhenKindsAreSet(): void
    {
        $filter = new Filter(kinds: [1]);

        $this->assertTrue($filter->hasKinds());
    }

    public function testHasKindsReturnsFalseWhenKindsAreNull(): void
    {
        $filter = new Filter();

        $this->assertFalse($filter->hasKinds());
    }

    public function testHasLimitReturnsTrueWhenLimitIsSet(): void
    {
        $filter = new Filter(limit: 100);

        $this->assertTrue($filter->hasLimit());
    }

    public function testHasLimitReturnsFalseWhenLimitIsNull(): void
    {
        $filter = new Filter();

        $this->assertFalse($filter->hasLimit());
    }

    public function testWithAuthorsReturnsNewFilterWithUpdatedAuthors(): void
    {
        $filter = new Filter(
            ids: [str_repeat('a', 64)],
            authors: [str_repeat('c', 64)],
            kinds: [1],
            limit: 10
        );

        $newFilter = $filter->withAuthors([str_repeat('d', 64), str_repeat('e', 64)]);

        $this->assertAuthorHexes([str_repeat('c', 64)], $filter->getAuthors());
        $this->assertAuthorHexes([str_repeat('d', 64), str_repeat('e', 64)], $newFilter->getAuthors());
        $this->assertIdHexes([str_repeat('a', 64)], $newFilter->getIds());
        $this->assertKinds([1], $newFilter->getKinds());
        $this->assertSame(10, $newFilter->getLimit());
    }

    public function testWithKindsReturnsNewFilterWithReplacedKinds(): void
    {
        $filter = new Filter(
            authors: [str_repeat('f', 64)],
            kinds: [1, 2],
            limit: 10
        );

        $newFilter = $filter->withKinds([0, 7, 30023]);

        $this->assertKinds([1, 2], $filter->getKinds());
        $this->assertKinds([0, 7, 30023], $newFilter->getKinds());
        $this->assertAuthorHexes([str_repeat('f', 64)], $newFilter->getAuthors());
        $this->assertSame(10, $newFilter->getLimit());
    }

    public function testMatchesEventBySinceTimestamp(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::fromInt(1234567895),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filterMatches = new Filter(since: Timestamp::fromInt(1234567890));
        $filterDoesNotMatch = new Filter(since: Timestamp::fromInt(1234567900));

        $this->assertTrue($filterMatches->matches($event));
        $this->assertFalse($filterDoesNotMatch->matches($event));
    }

    public function testMatchesEventByUntilTimestamp(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::fromInt(1234567895),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filterMatches = new Filter(until: Timestamp::fromInt(1234567900));
        $filterDoesNotMatch = new Filter(until: Timestamp::fromInt(1234567890));

        $this->assertTrue($filterMatches->matches($event));
        $this->assertFalse($filterDoesNotMatch->matches($event));
    }

    public function testMatchesEmptyFilterMatchesAnyEvent(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter();

        $this->assertTrue($filter->matches($event));
    }

    public function testDoesNotMatchEventWithWrongId(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );
        $signedEvent = $event->sign($keyPair, FakeSignatureService::accepting());

        $filter = new Filter(ids: ['0000000000000000000000000000000000000000000000000000000000000000']);

        $this->assertFalse($filter->matches($signedEvent));
    }

    public function testDoesNotMatchEventWithWrongAuthor(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(authors: [str_repeat('0', 64)]);

        $this->assertFalse($filter->matches($event));
    }

    public function testThrowsExceptionForLimitAboveMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 5000');

        new Filter(limit: 5001);
    }

    public function testToArrayOmitsNullFields(): void
    {
        $filter = new Filter(kinds: [1]);

        $array = $filter->toArray();

        $this->assertArrayHasKey('kinds', $array);
        $this->assertArrayNotHasKey('ids', $array);
        $this->assertArrayNotHasKey('authors', $array);
        $this->assertArrayNotHasKey('since', $array);
        $this->assertArrayNotHasKey('until', $array);
        $this->assertArrayNotHasKey('limit', $array);
    }

    public function testToArrayConvertsEventKindObjectsToIntegers(): void
    {
        $filter = new Filter(kinds: [EventKind::fromInt(EventKind::TEXT_NOTE)]);

        $array = $filter->toArray();

        $this->assertSame([1], $array['kinds']);
    }

    public function testFromArrayHandlesMultipleTagTypes(): void
    {
        $data = [
            '#t' => ['nostr'],
            '#p' => [str_repeat('a', 64)],
        ];

        $filter = Filter::fromArray($data);

        $this->assertNotNull($filter);
        $tags = $filter->getTags();
        $this->assertNotNull($tags);
        $this->assertSame(['nostr'], $tags['t']);
        $this->assertSame([str_repeat('a', 64)], $tags['p']);
    }

    public function testFromArrayWithoutTagsReturnsNullTags(): void
    {
        $data = ['kinds' => [1]];

        $filter = Filter::fromArray($data);

        $this->assertNotNull($filter);
        $this->assertNull($filter->getTags());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedFilterProvider(): iterable
    {
        yield 'kinds not an array' => [['kinds' => 'one']];
        yield 'kinds with non-int element' => [['kinds' => ['1']]];
        yield 'ids not an array' => [['ids' => str_repeat('a', 64)]];
        yield 'authors not an array' => [['authors' => str_repeat('a', 64)]];
        yield 'limit not an int' => [['limit' => '5']];
        yield 'search not a string' => [['search' => ['nostr']]];
        yield 'since not an int' => [['since' => '1700000000']];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('malformedFilterProvider')]
    public function testFromArrayReturnsNullForMalformedScalarFields(array $data): void
    {
        $this->assertNull(Filter::fromArray($data));
    }

    public function testToStringReturnsJsonRepresentation(): void
    {
        $filter = new Filter(kinds: [1], limit: 10);

        $string = (string) $filter;

        $decoded = json_decode($string, true);
        $this->assertIsArray($decoded);
        $this->assertSame([1], $decoded['kinds']);
        $this->assertSame(10, $decoded['limit']);
    }

    public function testRoundTripFromArrayToArray(): void
    {
        $data = [
            'ids' => ['abc123'],
            'authors' => ['def456'],
            'kinds' => [1, 7],
            '#t' => ['nostr'],
            'since' => 1234567890,
            'until' => 1234567900,
            'limit' => 50,
        ];

        $filter = Filter::fromArray($data);

        $this->assertNotNull($filter);
        $this->assertSame($data, $filter->toArray());
    }

    public function testMatchesSearchTermInContent(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr world')
        );

        $filter = new Filter(search: 'nostr');

        $this->assertTrue($filter->matches($event));
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello NOSTR World')
        );

        $filter = new Filter(search: 'nostr world');

        $this->assertTrue($filter->matches($event));
    }

    public function testSearchRequiresAllTerms(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr')
        );

        $filter = new Filter(search: 'nostr bitcoin');

        $this->assertFalse($filter->matches($event));
    }

    public function testSearchDoesNotMatchWhenTermAbsent(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello world')
        );

        $filter = new Filter(search: 'nostr');

        $this->assertFalse($filter->matches($event));
    }

    public function testSearchCombinesWithOtherFilters(): void
    {
        $keyPair = KeyMother::alice();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr')
        );

        $matchingFilter = new Filter(kinds: [1], search: 'nostr');
        $nonMatchingFilter = new Filter(kinds: [2], search: 'nostr');

        $this->assertTrue($matchingFilter->matches($event));
        $this->assertFalse($nonMatchingFilter->matches($event));
    }

    public function testHasSearchReturnsTrueWhenSet(): void
    {
        $filter = new Filter(search: 'nostr');

        $this->assertTrue($filter->hasSearch());
        $this->assertSame('nostr', $filter->getSearch());
    }

    public function testHasSearchReturnsFalseWhenNull(): void
    {
        $filter = new Filter();

        $this->assertFalse($filter->hasSearch());
        $this->assertNull($filter->getSearch());
    }

    public function testSearchRoundTripFromArrayToArray(): void
    {
        $data = [
            'kinds' => [1],
            'search' => 'nostr protocol',
        ];

        $filter = Filter::fromArray($data);

        $this->assertNotNull($filter);
        $this->assertSame($data, $filter->toArray());
    }

    public function testToArrayOmitsSearchWhenNull(): void
    {
        $filter = new Filter(kinds: [1]);

        $array = $filter->toArray();

        $this->assertArrayNotHasKey('search', $array);
    }

    public function testWithAuthorsPreservesSearch(): void
    {
        $filter = new Filter(search: 'nostr');

        $newFilter = $filter->withAuthors(['new-author']);

        $this->assertSame('nostr', $newFilter->getSearch());
    }

    private function assertKinds(array $expectedInts, ?array $actualKinds): void
    {
        $this->assertNotNull($actualKinds);
        $this->assertSame(
            $expectedInts,
            array_map(static fn (EventKind $k) => $k->toInt(), $actualKinds)
        );
    }

    /**
     * @param list<string> $expectedHexes
     */
    private function assertIdHexes(array $expectedHexes, ?EventIdCollection $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame(
            $expectedHexes,
            array_map(static fn (EventId $id): string => $id->toHex(), $actual->toArray())
        );
    }

    /**
     * @param list<string> $expectedHexes
     */
    private function assertAuthorHexes(array $expectedHexes, ?PublicKeyCollection $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame(
            $expectedHexes,
            array_map(static fn (PublicKey $pk): string => $pk->toHex(), $actual->toArray())
        );
    }

    public function testConstructorRejectsIdsExceedingMaxValues(): void
    {
        $ids = array_fill(0, Filter::MAX_VALUES_PER_FIELD + 1, str_repeat('0', 64));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may contain at most');

        new Filter(ids: $ids);
    }

    public function testConstructorRejectsAuthorsExceedingMaxValues(): void
    {
        $authors = array_fill(0, Filter::MAX_VALUES_PER_FIELD + 1, str_repeat('0', 64));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may contain at most');

        new Filter(authors: $authors);
    }

    public function testConstructorRejectsKindsExceedingMaxValues(): void
    {
        $kinds = array_fill(0, Filter::MAX_VALUES_PER_FIELD + 1, 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may contain at most');

        new Filter(kinds: $kinds);
    }

    public function testConstructorRejectsTagValuesExceedingMaxValues(): void
    {
        $values = array_fill(0, Filter::MAX_VALUES_PER_FIELD + 1, 'abc');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may contain at most');

        new Filter(tags: ['e' => $values]);
    }

    public function testFromArrayReturnsNullForEmptyTagName(): void
    {
        $this->assertNull(Filter::fromArray(['#' => ['value']]));
    }

    public function testFromArrayReturnsNullForNonArrayTagValues(): void
    {
        $this->assertNull(Filter::fromArray(['#e' => 'not-an-array']));
    }

    public function testEmptyFilterJsonSerialisesAsAnObject(): void
    {
        $this->assertSame('{}', json_encode((new Filter())->jsonSerialize(), JSON_THROW_ON_ERROR));
    }

    public function testNonEmptyFilterJsonSerialisesWithItsArrayShape(): void
    {
        $this->assertSame('{"kinds":[1]}', json_encode((new Filter(kinds: [1]))->jsonSerialize(), JSON_THROW_ON_ERROR));
    }

    public function testEmptyFilterCastsToStringAsAnObject(): void
    {
        $this->assertSame('{}', (string) new Filter());
    }

    public function testCastToStringAgreesWithJsonSerialisation(): void
    {
        $filter = new Filter(kinds: [1], authors: ['abc']);

        $this->assertSame(json_encode($filter->jsonSerialize(), JSON_THROW_ON_ERROR), (string) $filter);
    }

    public function testEmptyFilterRoundTripsThroughTheJsonForm(): void
    {
        $restored = Filter::fromArray((new Filter())->jsonSerialize());

        $this->assertNotNull($restored);
        $this->assertSame([], $restored->toArray());
    }
}
