<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    public function testCanCreateFilter(): void
    {
        $filter = new Filter(
            ids: ['event-id'],
            authors: ['author-pubkey'],
            kinds: [1, 2],
            tags: ['t' => ['nostr']],
            since: Timestamp::fromInt(1234567890),
            until: Timestamp::fromInt(1234567900),
            limit: 10
        );

        $this->assertSame(['event-id'], $filter->getIds());
        $this->assertSame(['author-pubkey'], $filter->getAuthors());
        $this->assertSame([1, 2], $filter->getKinds());
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
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );
        $signedEvent = $event->sign($keyPair->getPrivateKey());

        $filter = new Filter(ids: [$signedEvent->getId()->toHex()]);

        $this->assertTrue($filter->matches($signedEvent));
    }

    public function testMatchesEventByAuthor(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(authors: [$keyPair->getPublicKey()->toHex()]);

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventByKind(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(kinds: [1]);

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventByTag(): void
    {
        $keyPair = KeyPair::generate();
        $tags = new TagCollection([Tag::hashtag('nostr')]);
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('test')
        );

        $filter = new Filter(tags: ['t' => ['nostr']]);

        $this->assertTrue($filter->matches($event));
    }

    public function testMatchesEventWithMultipleTagTypesRequiresAll(): void
    {
        $keyPair = KeyPair::generate();
        $pubkeyHex = str_repeat('a', 64);
        $tags = new TagCollection([
            Tag::hashtag('nostr'),
            Tag::pubkey($pubkeyHex),
        ]);
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
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

    public function testMatchesEventWithMultipleValuesInSameTagType(): void
    {
        $keyPair = KeyPair::generate();
        $tags = new TagCollection([Tag::hashtag('nostr')]);
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('test')
        );

        $filter = new Filter(tags: ['t' => ['nostr', 'bitcoin']]);
        $this->assertTrue($filter->matches($event));
    }

    public function testDoesNotMatchWhenNoTagsMatchFilter(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter(tags: ['t' => ['nostr']]);
        $this->assertFalse($filter->matches($event));
    }

    public function testDoesNotMatchWhenCriteriaNotMet(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
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
            'ids' => ['event-id'],
            'authors' => ['author-pubkey'],
            'kinds' => [1],
            '#t' => ['nostr'],
            'since' => 1234567890,
            'until' => 1234567900,
            'limit' => 10,
        ];

        $filter = Filter::fromArray($data);

        $this->assertSame(['event-id'], $filter->getIds());
        $this->assertSame(['author-pubkey'], $filter->getAuthors());
        $this->assertSame([1], $filter->getKinds());
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
            ids: ['event-id'],
            authors: ['original-author'],
            kinds: [1],
            limit: 10
        );

        $newFilter = $filter->withAuthors(['new-author-1', 'new-author-2']);

        $this->assertSame(['original-author'], $filter->getAuthors());
        $this->assertSame(['new-author-1', 'new-author-2'], $newFilter->getAuthors());
        $this->assertSame(['event-id'], $newFilter->getIds());
        $this->assertSame([1], $newFilter->getKinds());
        $this->assertSame(10, $newFilter->getLimit());
    }

    public function testMatchesEventBySinceTimestamp(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::fromInt(1234567895),
            EventKind::textNote(),
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
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::fromInt(1234567895),
            EventKind::textNote(),
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
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $filter = new Filter();

        $this->assertTrue($filter->matches($event));
    }

    public function testDoesNotMatchEventWithWrongId(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );
        $signedEvent = $event->sign($keyPair->getPrivateKey());

        $filter = new Filter(ids: ['0000000000000000000000000000000000000000000000000000000000000000']);

        $this->assertFalse($filter->matches($signedEvent));
    }

    public function testDoesNotMatchEventWithWrongAuthor(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
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
        $filter = new Filter(kinds: [EventKind::textNote()]);

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

        $tags = $filter->getTags();
        $this->assertNotNull($tags);
        $this->assertSame(['nostr'], $tags['t']);
        $this->assertSame([str_repeat('a', 64)], $tags['p']);
    }

    public function testFromArrayWithoutTagsReturnsNullTags(): void
    {
        $data = ['kinds' => [1]];

        $filter = Filter::fromArray($data);

        $this->assertNull($filter->getTags());
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
        $output = $filter->toArray();

        $this->assertSame($data, $output);
    }
}
