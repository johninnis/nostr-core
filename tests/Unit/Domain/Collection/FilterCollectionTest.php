<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class FilterCollectionTest extends TestCase
{
    public function testMatchesReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse(new FilterCollection()->matches($this->textNote()));
    }

    public function testMatchesReturnsTrueWhenAnyFilterMatches(): void
    {
        $filters = new FilterCollection([
            Filter::from(kinds: EventKindCollection::fromInts([EventKind::METADATA])),
            Filter::from(kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE])),
        ]);

        $this->assertTrue($filters->matches($this->textNote()));
    }

    public function testMatchesReturnsFalseWhenNoFilterMatches(): void
    {
        $filters = new FilterCollection([
            Filter::from(kinds: EventKindCollection::fromInts([EventKind::METADATA])),
            Filter::from(kinds: EventKindCollection::fromInts([EventKind::REACTION])),
        ]);

        $this->assertFalse($filters->matches($this->textNote()));
    }

    private function textNote(): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));
    }

    public function testMatchableKeepsOnlyTheFiltersThatCanMatch(): void
    {
        $matchable = Filter::from(kinds: EventKindCollection::fromInts([1]));

        $filters = new FilterCollection([Filter::from(ids: new EventIdCollection()), $matchable, Filter::from(authors: new PublicKeyCollection())]);

        $this->assertSame([$matchable], $filters->matchable()->toArray());
    }

    public function testMatchableIsEmptyWhenNoFilterCanMatch(): void
    {
        $filters = new FilterCollection([Filter::from(since: Timestamp::fromInt(2), until: Timestamp::fromInt(1))]);

        $this->assertTrue($filters->matchable()->isEmpty());
    }
}
