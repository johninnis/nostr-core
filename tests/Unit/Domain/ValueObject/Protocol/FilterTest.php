<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FilterTest extends TestCase
{
    public function testCanCreateFilter(): void
    {
        $filter = Filter::from(
            ids: EventIdCollection::fromHexValues([str_repeat('a', 64)]),
            authors: PublicKeyCollection::fromHexValues([str_repeat('b', 64)]),
            kinds: EventKindCollection::fromInts([1, 2]),
            tags: TagFilter::fromValues(['t' => ['nostr']]),
            since: Timestamp::fromInt(1234567890),
            until: Timestamp::fromInt(1234567900),
            limit: 10
        );

        $this->assertIdHexes([str_repeat('a', 64)], $filter->getIds());
        $this->assertAuthorHexes([str_repeat('b', 64)], $filter->getAuthors());
        $this->assertKinds([1, 2], $filter->getKinds());
        $this->assertSame(['t' => ['nostr']], $filter->getTags()?->getValues());
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

        Filter::from(limit: -1);
    }

    public function testAcceptsLimitOfZero(): void
    {
        $filter = Filter::from(limit: 0);

        $this->assertSame(0, $filter->getLimit());
        $this->assertNotNull($filter->getLimit());
        $this->assertSame(0, $filter->toArray()['limit']);
    }

    public function testTryFromRefusesASearchThatIsNotUtf8(): void
    {
        $this->assertNull(Filter::tryFrom(search: "\xff"));
    }

    public function testAFilterWhoseSinceIsAfterItsUntilCannotMatch(): void
    {
        $filter = Filter::from(since: Timestamp::fromInt(1234567900), until: Timestamp::fromInt(1234567890));

        $this->assertFalse($filter->canMatch());
    }

    public function testAFilterWhoseSinceEqualsItsUntilCanMatch(): void
    {
        $this->assertTrue(Filter::from(since: Timestamp::fromInt(1234567890), until: Timestamp::fromInt(1234567890))->canMatch());
    }

    public function testTheEmptyFilterCanMatch(): void
    {
        $this->assertTrue(Filter::from()->canMatch());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filtersWithAnEmptyList(): iterable
    {
        yield 'ids' => ['{"ids":[]}'];
        yield 'authors' => ['{"authors":[]}'];
        yield 'kinds' => ['{"kinds":[]}'];
        yield 'a tag condition' => ['{"#e":[]}'];
    }

    #[DataProvider('filtersWithAnEmptyList')]
    public function testAFilterWithAnEmptyListCannotMatch(string $json): void
    {
        $this->assertFalse((Filter::tryFromJson($json) ?? $this->fail('Expected a filter'))->canMatch());
    }

    #[DataProvider('filtersWithAnEmptyList')]
    public function testAFilterWithAnEmptyListMatchesNoEvent(string $json): void
    {
        $filter = Filter::tryFromJson($json) ?? $this->fail('Expected a filter');
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            tags: new TagCollection([Tag::fromArray(['e', str_repeat('a', 64)])]),
        ));

        $this->assertFalse($filter->matches($event));
    }

    public function testTryFromArrayRefusesATagConditionNotNamedByOneLetter(): void
    {
        $this->assertNull(Filter::tryFromArray(['#emoji' => ['wave']]));
    }

    public function testMatchesEventById(): void
    {
        $keyPair = KeyMother::alice();
        $rumour = Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        );
        $signedEvent = $rumour->sign($keyPair, FakeSignatureService::accepting());

        $filter = Filter::from(ids: EventIdCollection::fromHexValues([$signedEvent->getId()->toHex()]));

        $this->assertTrue($filter->matches($signedEvent));
    }

    public function testMatchesEventByAuthor(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(authors: PublicKeyCollection::fromHexValues([$keyPair->getPublicKey()->toHex()]));

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventByKind(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventByTag(): void
    {
        $keyPair = KeyMother::alice();
        $tags = new TagCollection([Tag::hashtag(Hashtag::fromString('nostr'))]);
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            $tags,
            Timestamp::now(),
        ));

        $filter = Filter::from(tags: TagFilter::fromValues(['t' => ['nostr']]));

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventAtTheMaximumAuthorCount(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $authors = array_map(
            static fn (int $i): string => str_pad(dechex($i), 64, '0', STR_PAD_LEFT),
            range(1, 999),
        );
        $authors[] = $keyPair->getPublicKey()->toHex();

        $this->assertTrue(Filter::from(authors: PublicKeyCollection::fromHexValues($authors))->matches($event));
    }

    public function testTagFilterMatchesAnEventCarryingManyTags(): void
    {
        $keyPair = KeyMother::alice();
        $tags = [];
        for ($i = 0; $i < 1000; ++$i) {
            $tags[] = Tag::hashtag(Hashtag::fromString("noise{$i}"));
        }
        $tags[] = Tag::hashtag(Hashtag::fromString('target'));

        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection($tags),
            Timestamp::now(),
        ));

        $this->assertTrue(Filter::from(tags: TagFilter::fromValues(['t' => ['target']]))->matches($event));
    }

    public function testMatchesEventWithMultipleTagTypesRequiresAll(): void
    {
        $keyPair = KeyMother::alice();
        $pubkeyHex = str_repeat('a', 64);
        $tags = new TagCollection([
            Tag::hashtag(Hashtag::fromString('nostr')),
            Tag::fromArray(['p', $pubkeyHex]),
        ]);
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            $tags,
            Timestamp::now(),
        ));

        $filterBothMatch = Filter::from(tags: TagFilter::fromValues(['t' => ['nostr'], 'p' => [$pubkeyHex]]));
        $this->assertTrue($filterBothMatch->matches($event));

        $filterOnlyPMissing = Filter::from(tags: TagFilter::fromValues(['t' => ['nostr'], 'p' => [str_repeat('b', 64)]]));
        $this->assertFalse($filterOnlyPMissing->matches($event));

        $filterOnlyTMissing = Filter::from(tags: TagFilter::fromValues(['t' => ['bitcoin'], 'p' => [$pubkeyHex]]));
        $this->assertFalse($filterOnlyTMissing->matches($event));
    }

    public function testTagFilterMatchesOnlyTheTagValuePosition(): void
    {
        $pubkey = PublicKey::tryFromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test public key');
        $referencedId = str_repeat('c', 64);
        $referencedAuthor = str_repeat('d', 64);
        $tags = new TagCollection([Tag::fromArray(['e', $referencedId, 'wss://relay.example', 'reply', $referencedAuthor])]);
        $event = EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            $tags,
            Timestamp::now(),
        ));

        $this->assertTrue(Filter::from(tags: TagFilter::fromValues(['e' => [$referencedId]]))->matches($event));
        $this->assertFalse(Filter::from(tags: TagFilter::fromValues(['e' => [$referencedAuthor]]))->matches($event));
    }

    public function testMatchesEventWithMultipleValuesInSameTagType(): void
    {
        $keyPair = KeyMother::alice();
        $tags = new TagCollection([Tag::hashtag(Hashtag::fromString('nostr'))]);
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            $tags,
            Timestamp::now(),
        ));

        $filter = Filter::from(tags: TagFilter::fromValues(['t' => ['nostr', 'bitcoin']]));
        $this->assertTrue($filter->matches($event));
    }

    public function testDoesNotMatchWhenNoTagsMatchFilter(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(tags: TagFilter::fromValues(['t' => ['nostr']]));
        $this->assertFalse($filter->matches($event));
    }

    public function testDoesNotMatchWhenCriteriaNotMet(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(kinds: EventKindCollection::fromInts([2]));

        $this->assertFalse($filter->matches($event));
    }

    public function testCanConvertToArray(): void
    {
        $filter = Filter::from(
            ids: EventIdCollection::fromHexValues([str_repeat('a', 64)]),
            authors: PublicKeyCollection::fromHexValues([str_repeat('b', 64)]),
            kinds: EventKindCollection::fromInts([1]),
            tags: TagFilter::fromValues(['t' => ['nostr']]),
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

        $filter = Filter::tryFromArray($data);

        $this->assertNotNull($filter);
        $this->assertIdHexes([str_repeat('a', 64)], $filter->getIds());
        $this->assertAuthorHexes([str_repeat('b', 64)], $filter->getAuthors());
        $this->assertKinds([1], $filter->getKinds());
        $this->assertSame(['t' => ['nostr']], $filter->getTags()?->getValues());
        $since = $filter->getSince();
        $until = $filter->getUntil();
        $this->assertNotNull($since);
        $this->assertNotNull($until);
        $this->assertSame(1234567890, $since->toInt());
        $this->assertSame(1234567900, $until->toInt());
        $this->assertSame(10, $filter->getLimit());
    }

    public function testGetIdsIsPresentWhenIdsAreSet(): void
    {
        $filter = Filter::from(ids: EventIdCollection::fromHexValues([str_repeat('a', 64)]));

        $this->assertNotNull($filter->getIds());
    }

    public function testGetIdsIsAbsentWhenIdsAreNull(): void
    {
        $filter = Filter::from();

        $this->assertNull($filter->getIds());
    }

    public function testGetAuthorsIsPresentWhenAuthorsAreSet(): void
    {
        $filter = Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('b', 64)]));

        $this->assertNotNull($filter->getAuthors());
    }

    public function testGetAuthorsIsAbsentWhenAuthorsAreNull(): void
    {
        $filter = Filter::from();

        $this->assertNull($filter->getAuthors());
    }

    public function testGetKindsIsPresentWhenKindsAreSet(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));

        $this->assertNotNull($filter->getKinds());
    }

    public function testGetKindsIsAbsentWhenKindsAreNull(): void
    {
        $filter = Filter::from();

        $this->assertNull($filter->getKinds());
    }

    public function testGetLimitIsPresentWhenLimitIsSet(): void
    {
        $filter = Filter::from(limit: 100);

        $this->assertNotNull($filter->getLimit());
    }

    public function testGetLimitIsAbsentWhenLimitIsNull(): void
    {
        $filter = Filter::from();

        $this->assertNull($filter->getLimit());
    }

    public function testWithAuthorsReturnsNewFilterWithUpdatedAuthors(): void
    {
        $filter = Filter::from(
            ids: EventIdCollection::fromHexValues([str_repeat('a', 64)]),
            authors: PublicKeyCollection::fromHexValues([str_repeat('c', 64)]),
            kinds: EventKindCollection::fromInts([1]),
            limit: 10
        );

        $newFilter = $filter->withAuthors(PublicKeyCollection::fromHexValues([str_repeat('d', 64), str_repeat('e', 64)]));

        $this->assertAuthorHexes([str_repeat('c', 64)], $filter->getAuthors());
        $this->assertAuthorHexes([str_repeat('d', 64), str_repeat('e', 64)], $newFilter->getAuthors());
        $this->assertIdHexes([str_repeat('a', 64)], $newFilter->getIds());
        $this->assertKinds([1], $newFilter->getKinds());
        $this->assertSame(10, $newFilter->getLimit());
    }

    public function testWithKindsReturnsNewFilterWithReplacedKinds(): void
    {
        $filter = Filter::from(
            authors: PublicKeyCollection::fromHexValues([str_repeat('f', 64)]),
            kinds: EventKindCollection::fromInts([1, 2]),
            limit: 10
        );

        $newFilter = $filter->withKinds(EventKindCollection::fromInts([0, 7, 30023]));

        $this->assertKinds([1, 2], $filter->getKinds());
        $this->assertKinds([0, 7, 30023], $newFilter->getKinds());
        $this->assertAuthorHexes([str_repeat('f', 64)], $newFilter->getAuthors());
        $this->assertSame(10, $newFilter->getLimit());
    }

    public function testWithUntilReturnsNewFilterWithReplacedUntil(): void
    {
        $filter = Filter::from(
            kinds: EventKindCollection::fromInts([1]),
            until: Timestamp::fromInt(1234567900),
            limit: 10
        );

        $newFilter = $filter->withUntil(Timestamp::fromInt(1234567800));

        $this->assertSame(1234567900, $filter->getUntil()?->toInt());
        $this->assertSame(1234567800, $newFilter->getUntil()?->toInt());
        $this->assertKinds([1], $newFilter->getKinds());
        $this->assertSame(10, $newFilter->getLimit());
    }

    public function testWithSinceReturnsNewFilterWithReplacedSince(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));

        $newFilter = $filter->withSince(Timestamp::fromInt(1234567890));

        $this->assertNull($filter->getSince());
        $this->assertSame(1234567890, $newFilter->getSince()?->toInt());
    }

    public function testWithUntilNullClearsTheUpperBound(): void
    {
        $filter = Filter::from(until: Timestamp::fromInt(1234567900));

        $this->assertNull($filter->withUntil(null)->getUntil());
    }

    public function testWithLimitReturnsNewFilterWithReplacedLimit(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]), limit: 10);

        $newFilter = $filter->withLimit(50);

        $this->assertSame(10, $filter->getLimit());
        $this->assertSame(50, $newFilter->getLimit());
        $this->assertKinds([1], $newFilter->getKinds());
    }

    public function testWithUntilBeforeSinceGivesAFilterThatCannotMatch(): void
    {
        $filter = Filter::from(since: Timestamp::fromInt(1234567900))->withUntil(Timestamp::fromInt(1234567800));

        $this->assertFalse($filter->canMatch());
    }

    public function testMatchesEventBySinceTimestamp(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::fromInt(1234567895),
        ));

        $filterMatches = Filter::from(since: Timestamp::fromInt(1234567890));
        $filterDoesNotMatch = Filter::from(since: Timestamp::fromInt(1234567900));

        $this->assertTrue($filterMatches->matches($event));
        $this->assertFalse($filterDoesNotMatch->matches($event));
    }

    public function testMatchesEventByUntilTimestamp(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::fromInt(1234567895),
        ));

        $filterMatches = Filter::from(until: Timestamp::fromInt(1234567900));
        $filterDoesNotMatch = Filter::from(until: Timestamp::fromInt(1234567890));

        $this->assertTrue($filterMatches->matches($event));
        $this->assertFalse($filterDoesNotMatch->matches($event));
    }

    public function testMatchesEmptyFilterMatchesAnyEvent(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from();

        $this->assertTrue($filter->matches($event));
    }

    public function testDoesNotMatchEventWithWrongId(): void
    {
        $keyPair = KeyMother::alice();
        $rumour = Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        );
        $signedEvent = $rumour->sign($keyPair, FakeSignatureService::accepting());

        $filter = Filter::from(ids: EventIdCollection::fromHexValues(['0000000000000000000000000000000000000000000000000000000000000000']));

        $this->assertFalse($filter->matches($signedEvent));
    }

    public function testDoesNotMatchEventWithWrongAuthor(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('0', 64)]));

        $this->assertFalse($filter->matches($event));
    }

    public function testKeepsALimitOfAnySizeAsTheRelayDecidesItsCeiling(): void
    {
        $filter = Filter::from(limit: PHP_INT_MAX);

        $this->assertSame(PHP_INT_MAX, $filter->getLimit());
    }

    public function testToArrayOmitsNullFields(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));

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
        $filter = Filter::from(kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]));

        $array = $filter->toArray();

        $this->assertSame([1], $array['kinds']);
    }

    public function testTryFromArrayHandlesMultipleTagTypes(): void
    {
        $data = [
            '#t' => ['nostr'],
            '#p' => [str_repeat('a', 64)],
        ];

        $filter = Filter::tryFromArray($data);

        $this->assertNotNull($filter);
        $tags = $filter->getTags();
        $this->assertNotNull($tags);
        $this->assertSame(['nostr'], $tags->getValues()['t']);
        $this->assertSame([str_repeat('a', 64)], $tags->getValues()['p']);
    }

    public function testTryFromArrayWithoutTagsReturnsNullTags(): void
    {
        $data = ['kinds' => [1]];

        $filter = Filter::tryFromArray($data);

        $this->assertNotNull($filter);
        $this->assertNull($filter->getTags());
    }

    public function testTryFromArrayReturnsNullForInvalidUtf8Search(): void
    {
        $this->assertNull(Filter::tryFromArray(['search' => "bad\xff\xfeutf8"]));
    }

    public function testTryFromArrayReturnsNullForInvalidUtf8TagValue(): void
    {
        $this->assertNull(Filter::tryFromArray(['#t' => ["bad\xff\xfeutf8"]]));
    }

    public function testTryFromArrayParsesAnArrayPayload(): void
    {
        $this->assertEquals(Filter::tryFromArray(['kinds' => [1]]), Filter::tryFromArray(['kinds' => [1]]));
    }

    #[DataProvider('nonArrayWireValues')]
    public function testTryFromArrayReturnsNullForNonArrayPayload(mixed $value): void
    {
        $this->assertNull(Filter::tryFromArray($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonArrayWireValues(): iterable
    {
        yield 'string' => ['not-a-filter'];
        yield 'int' => [42];
        yield 'bool' => [true];
        yield 'null' => [null];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedFilterProvider(): iterable
    {
        yield 'kinds not an array' => [['kinds' => 'one']];
        yield 'kinds with non-int element' => [['kinds' => ['1']]];
        yield 'kinds with out-of-range value' => [['kinds' => [70000]]];
        yield 'ids not an array' => [['ids' => str_repeat('a', 64)]];
        yield 'ids with non-string element' => [['ids' => [123]]];
        yield 'authors not an array' => [['authors' => str_repeat('a', 64)]];
        yield 'authors with non-string element' => [['authors' => [123]]];
        yield 'tag values with a non-string element' => [['#e' => [str_repeat('a', 64), 123]]];
        yield 'tag values with a nested-array element' => [['#p' => [['nested']]]];
        yield 'limit not an int' => [['limit' => '5']];
        yield 'limit below minimum' => [['limit' => -1]];
        yield 'search not a string' => [['search' => ['nostr']]];
        yield 'since not an int' => [['since' => '1700000000']];
        yield 'since negative' => [['since' => -1]];
        yield 'until negative' => [['until' => -1]];
        yield 'a tag condition named by two letters' => [['#tt' => ['x']]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('malformedFilterProvider')]
    public function testTryFromArrayReturnsNullForMalformedScalarFields(array $data): void
    {
        $this->assertNull(Filter::tryFromArray($data));
    }

    #[DataProvider('fieldsStatedAsNull')]
    public function testTryFromJsonRefusesAFieldStatedAsNull(string $json): void
    {
        $this->assertNull(Filter::tryFromJson($json));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fieldsStatedAsNull(): iterable
    {
        yield 'ids' => ['{"ids":null}'];
        yield 'authors' => ['{"authors":null}'];
        yield 'kinds' => ['{"kinds":null}'];
        yield 'since' => ['{"since":null}'];
        yield 'until' => ['{"until":null}'];
        yield 'limit' => ['{"limit":null}'];
        yield 'search' => ['{"search":null}'];
        yield 'a tag condition' => ['{"#e":null}'];
    }

    public function testTryFromArrayParsesASinceAfterItsUntilAsAFilterThatCannotMatch(): void
    {
        $this->assertFalse(Filter::tryFromArray(['since' => 2000, 'until' => 1000])?->canMatch());
    }

    public function testToStringReturnsJsonRepresentation(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]), limit: 10);

        $string = (string) $filter;

        $decoded = json_decode($string, true);
        $this->assertIsArray($decoded);
        $this->assertSame([1], $decoded['kinds']);
        $this->assertSame(10, $decoded['limit']);
    }

    public function testRoundTripFromArrayToArray(): void
    {
        $data = [
            'ids' => [str_repeat('a', 64)],
            'authors' => [str_repeat('b', 64)],
            'kinds' => [1, 7],
            '#t' => ['nostr'],
            'since' => 1234567890,
            'until' => 1234567900,
            'limit' => 50,
        ];

        $filter = Filter::tryFromArray($data);

        $this->assertNotNull($filter);
        $this->assertSame($data, $filter->toArray());
    }

    public function testTryFromArrayAcceptsLimitOfZero(): void
    {
        $filter = Filter::tryFromArray(['limit' => 0]);

        $this->assertNotNull($filter);
        $this->assertSame(0, $filter->getLimit());
        $this->assertSame(['limit' => 0], $filter->toArray());
    }

    public function testTryFromArrayKeepsALimitAboveFiveThousand(): void
    {
        $filter = Filter::tryFromArray(['limit' => 99999]);

        $this->assertSame(99999, $filter?->getLimit());
    }

    public function testMatchesSearchTermInContent(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(search: 'nostr');

        $this->assertTrue($filter->matches($event));
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello NOSTR World'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(search: 'nostr world');

        $this->assertTrue($filter->matches($event));
    }

    public function testSearchRequiresAllTerms(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(search: 'nostr bitcoin');

        $this->assertFalse($filter->matches($event));
    }

    public function testSearchDoesNotMatchWhenTermAbsent(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(search: 'nostr');

        $this->assertFalse($filter->matches($event));
    }

    #[DataProvider('searchTermSeparators')]
    public function testSearchSeparatesTermsByEachAsciiWhitespaceCharacter(string $separator): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('world, hello'),
        ));

        $this->assertTrue(Filter::from(search: 'hello'.$separator.'world')->matches($event));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function searchTermSeparators(): iterable
    {
        yield 'space' => [' '];
        yield 'tab' => ["\t"];
        yield 'line feed' => ["\n"];
        yield 'vertical tab' => ["\v"];
        yield 'form feed' => ["\f"];
        yield 'carriage return' => ["\r"];
    }

    public function testSearchKeepsANoBreakSpaceInsideItsTerm(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('world, hello'),
        ));

        $this->assertFalse(Filter::from(search: "hello\u{A0}world")->matches($event));
    }

    public function testWhitespaceOnlySearchMatchesAnyEvent(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(search: '   ');

        $this->assertTrue($filter->matches($event));
    }

    public function testSearchIgnoresAKeyValueExtension(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertTrue(Filter::from(search: 'nostr language:en')->matches($event));
    }

    public function testSearchOfOnlyExtensionsMatchesWhatTheRestOfTheFilterMatches(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertTrue(Filter::from(kinds: EventKindCollection::fromInts([1]), search: 'include:spam domain:example.com Language:EN nsfw:false')->matches($event));
    }

    public function testSearchOfOnlyExtensionsStillAppliesTheRestOfTheFilter(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertFalse(Filter::from(kinds: EventKindCollection::fromInts([2]), search: 'nsfw:false')->matches($event));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function searchTokensThatAreNotExtensions(): iterable
    {
        yield 'a colon with nothing before it' => [':nostr'];
        yield 'a colon with nothing after it' => ['nostr:'];
        yield 'two colons' => ['a:b:c'];
        yield 'a url' => ['https://x.com'];
        yield 'a time' => ['12:30'];
        yield 'a key that starts with a digit' => ['1a:b'];
        yield 'a key holding a dot' => ['a.b:c'];
    }

    #[DataProvider('searchTokensThatAreNotExtensions')]
    public function testSearchReadsATokenThatIsNotAKeyValueExtensionAsATerm(string $term): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello world'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertFalse(Filter::from(search: $term)->matches($event));
    }

    public function testSearchCombinesWithOtherFilters(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $matchingFilter = Filter::from(kinds: EventKindCollection::fromInts([1]), search: 'nostr');
        $nonMatchingFilter = Filter::from(kinds: EventKindCollection::fromInts([2]), search: 'nostr');

        $this->assertTrue($matchingFilter->matches($event));
        $this->assertFalse($nonMatchingFilter->matches($event));
    }

    public function testGetSearchIsPresentWhenSet(): void
    {
        $filter = Filter::from(search: 'nostr');

        $this->assertNotNull($filter->getSearch());
        $this->assertSame('nostr', $filter->getSearch());
    }

    public function testGetSearchIsAbsentWhenNull(): void
    {
        $filter = Filter::from();

        $this->assertNull($filter->getSearch());
        $this->assertNull($filter->getSearch());
    }

    public function testSearchRoundTripFromArrayToArray(): void
    {
        $data = [
            'kinds' => [1],
            'search' => 'nostr protocol',
        ];

        $filter = Filter::tryFromArray($data);

        $this->assertNotNull($filter);
        $this->assertSame($data, $filter->toArray());
    }

    public function testToArrayOmitsSearchWhenNull(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));

        $array = $filter->toArray();

        $this->assertArrayNotHasKey('search', $array);
    }

    public function testWithAuthorsPreservesSearch(): void
    {
        $filter = Filter::from(search: 'nostr');

        $newFilter = $filter->withAuthors(PublicKeyCollection::fromHexValues([str_repeat('c', 64)]));

        $this->assertSame('nostr', $newFilter->getSearch());
    }

    /**
     * @param list<int> $expectedInts
     */
    private function assertKinds(array $expectedInts, ?EventKindCollection $actualKinds): void
    {
        $this->assertNotNull($actualKinds);
        $this->assertSame($expectedInts, $actualKinds->toInts());
    }

    /**
     * @param list<string> $expectedHexes
     */
    private function assertIdHexes(array $expectedHexes, ?EventIdCollection $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame($expectedHexes, $actual->toHexes());
    }

    /**
     * @param list<string> $expectedHexes
     */
    private function assertAuthorHexes(array $expectedHexes, ?PublicKeyCollection $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame($expectedHexes, $actual->toHexes());
    }

    public function testTryFromArrayParsesAFilterOfMoreThanAThousandAuthors(): void
    {
        $authors = array_map(static fn (int $i): string => str_pad(dechex($i), 64, '0', STR_PAD_LEFT), range(1, 1001));

        $this->assertCount(1001, Filter::tryFromArray(['authors' => $authors])?->getAuthors() ?? []);
    }

    public function testFromBuildsAFilterForAFollowListOfMoreThanAThousandKeys(): void
    {
        $follows = array_map(static fn (int $i): string => str_pad(dechex($i), 64, '0', STR_PAD_LEFT), range(1, 1500));

        $this->assertCount(1500, Filter::from(authors: PublicKeyCollection::fromHexValues($follows))->getAuthors() ?? []);
    }

    public function testFromBuildsAFilterOfMoreThanAThousandIdsKindsAndTagValues(): void
    {
        $filter = Filter::from(
            ids: EventIdCollection::fromHexValues(array_fill(0, 1001, str_repeat('0', 64))),
            kinds: EventKindCollection::fromInts(range(0, 1000)),
            tags: TagFilter::fromValues(['t' => array_fill(0, 1001, 'abc')]),
        );

        $this->assertSame([1001, 1001, 1001], [count($filter->getIds() ?? []), count($filter->getKinds() ?? []), count($filter->getTags()?->getValues()['t'] ?? [])]);
    }

    public function testTryFromArrayReturnsNullForEmptyTagName(): void
    {
        $this->assertNull(Filter::tryFromArray(['#' => ['value']]));
    }

    public function testTryFromArrayReturnsNullForNonArrayTagValues(): void
    {
        $this->assertNull(Filter::tryFromArray(['#e' => 'not-an-array']));
    }

    public function testEmptyFilterJsonSerialisesAsAnObject(): void
    {
        $this->assertSame('{}', json_encode(Filter::from()->jsonSerialize(), JSON_THROW_ON_ERROR));
    }

    public function testNonEmptyFilterJsonSerialisesWithItsArrayShape(): void
    {
        $this->assertSame('{"kinds":[1]}', json_encode(Filter::from(kinds: EventKindCollection::fromInts([1]))->jsonSerialize(), JSON_THROW_ON_ERROR));
    }

    public function testEmptyFilterCastsToStringAsAnObject(): void
    {
        $this->assertSame('{}', (string) Filter::from());
    }

    public function testCastToStringAgreesWithJsonSerialisation(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]), authors: PublicKeyCollection::fromHexValues([str_repeat('d', 64)]));

        $this->assertSame(json_encode($filter->jsonSerialize(), JSON_THROW_ON_ERROR), (string) $filter);
    }

    public function testEmptyFilterRoundTripsThroughTheJsonForm(): void
    {
        $restored = Filter::tryFromArray(Filter::from()->jsonSerialize());

        $this->assertNotNull($restored);
        $this->assertSame([], $restored->toArray());
    }

    public function testTryFromJsonRoundTripsWhatToStringProduces(): void
    {
        $filter = Filter::tryFromArray(['kinds' => [1], 'limit' => 10]) ?? self::fail('filter did not parse');

        $restored = Filter::tryFromJson((string) $filter);

        $this->assertNotNull($restored);
        $this->assertSame($filter->toArray(), $restored->toArray());
    }

    public function testTryFromJsonReturnsNullOnMalformedJson(): void
    {
        $this->assertNull(Filter::tryFromJson('{not json'));
    }

    public function testTryFromJsonReturnsNullOnJsonThatIsNotAnObject(): void
    {
        $this->assertNull(Filter::tryFromJson('"a string"'));
    }

    public function testAJsonListIsNotAFilter(): void
    {
        $this->assertNull(Filter::tryFromJson('["REQ","sub"]'));
        $this->assertNull(Filter::tryFromJson('[1,2]'));
    }

    public function testAnEmptyJsonObjectIsTheFilterThatMatchesEverything(): void
    {
        $filter = Filter::tryFromJson('{}');

        $this->assertNotNull($filter);
        $this->assertSame([], $filter->toArray());
    }

    public function testATryFromArrayListIsRefused(): void
    {
        $this->assertNull(Filter::tryFromArray(['REQ', 'sub']));
    }

    public function testAnObjectKeyedLikeAListIsReadAsAnObject(): void
    {
        $this->assertNotNull(Filter::tryFromJson('{"0":1}'));
    }

    public function testAnObjectKeyedLikeAListIgnoresItsKeyAsAnyUnknownKeyIs(): void
    {
        $listKeyed = Filter::tryFromJson('{"0":1}') ?? self::fail('{"0":1} did not parse');
        $unknownKeyed = Filter::tryFromJson('{"foo":1}') ?? self::fail('{"foo":1} did not parse');

        $this->assertSame($unknownKeyed->toArray(), $listKeyed->toArray());
    }

    public function testTryFromJsonRefusesAnEmptyJsonList(): void
    {
        $this->assertNull(Filter::tryFromJson('[]'));
    }

    public function testTryFromJsonRefusesAFieldGivenAsAnObjectKeyedLikeAList(): void
    {
        $this->assertNull(Filter::tryFromJson('{"kinds":{"0":1}}'));
    }

    public function testTryFromJsonRefusesAFieldGivenAsAnObjectWithNamedKeys(): void
    {
        $this->assertNull(Filter::tryFromJson('{"kinds":{"x":1}}'));
    }
}
