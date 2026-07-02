<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
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
            new Filter(kinds: EventKindCollection::fromInts([EventKind::METADATA])),
            new Filter(kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE])),
        ]);

        $this->assertTrue($filters->matches($this->textNote()));
    }

    public function testMatchesReturnsFalseWhenNoFilterMatches(): void
    {
        $filters = new FilterCollection([
            new Filter(kinds: EventKindCollection::fromInts([EventKind::METADATA])),
            new Filter(kinds: EventKindCollection::fromInts([EventKind::REACTION])),
        ]);

        $this->assertFalse($filters->matches($this->textNote()));
    }

    private function textNote(): Event
    {
        return EventMother::fromRumour(new Rumour(
            KeyMother::alice()->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('test'),
        ));
    }
}
