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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 5000');

        new Filter(limit: 0);
    }

    public function testThrowsExceptionForInvalidTimeRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
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
            'limit' => 10
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
}
