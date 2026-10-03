<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\RelayHintExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayHintExtractorProseTest extends TestCase
{
    private const string EVENT_ID = '5c83da77af1dec6d7289834998ad7aafbd9e2191396d75ec3cc27f5a77226f36';
    private const string IDENTIFIER = 'a-long-form-article';

    public function testReadsEveryRTagBesideAMentionInItsCanonicalForm(): void
    {
        $event = $this->noteWith(TagCollectionMother::fromRaw([
            ['e', self::EVENT_ID, '', 'mention'],
            ['r', 'wss://relay-one.example.com/'],
            ['r', 'wss://relay-two.example.com/'],
            ['r', 'wss://relay-three.example.com/'],
        ]));

        $this->assertSame(
            ['wss://relay-one.example.com', 'wss://relay-two.example.com', 'wss://relay-three.example.com'],
            $this->hints($event),
        );
    }

    public function testReadsAnAddressTagsRelayHintOnceBesideTheSameRTag(): void
    {
        $event = $this->noteWith(TagCollectionMother::fromRaw([
            ['a', $this->coordinate()->__toString(), 'wss://relay-one.example.com/', 'mention'],
            ['r', 'wss://relay-one.example.com/'],
            ['r', 'wss://relay-two.example.com/'],
        ]));

        $this->assertSame(['wss://relay-one.example.com', 'wss://relay-two.example.com'], $this->hints($event));
    }

    public function testReadsNoHintFromANeventWithoutRelaysInProse(): void
    {
        $nevent = Nevent::tryFromEventId($this->eventId()) ?? throw new RuntimeException('expected a valid nevent');

        $this->assertSame([], $this->hints($this->noteWith(new TagCollection(), "A paragraph of prose.\n\nnostr:".$nevent->toBech32().' ')));
    }

    public function testReadsTheRelaysOfAnNaddrInProse(): void
    {
        $naddr = Naddr::tryFromCoordinate($this->coordinate(), RelayUrlCollection::fromStrings(['wss://relay-one.example.com', 'wss://relay-two.example.com']))
            ?? throw new RuntimeException('expected a valid naddr');

        $this->assertSame(
            ['wss://relay-one.example.com', 'wss://relay-two.example.com'],
            $this->hints($this->noteWith(new TagCollection(), "A paragraph of prose.\n\nnostr:".$naddr->toBech32())),
        );
    }

    public function testReadsNoHintFromProseThatNamesNoRelay(): void
    {
        $this->assertSame([], $this->hints($this->noteWith(new TagCollection(), "A paragraph of prose.\n\nA second paragraph, naming nothing.")));
    }

    public function testReadsTheRTagsOfANoteWhoseContentNamesAnEventWithoutRelays(): void
    {
        $nevent = Nevent::tryFromEventId($this->eventId()) ?? throw new RuntimeException('expected a valid nevent');
        $event = $this->noteWith(
            TagCollectionMother::fromRaw([
                ['e', self::EVENT_ID, '', 'mention'],
                ['r', 'wss://relay-one.example.com/'],
                ['r', 'wss://relay-two.example.com/'],
            ]),
            "A paragraph of prose.\n\nnostr:".$nevent->toBech32(),
        );

        $this->assertSame(['wss://relay-one.example.com', 'wss://relay-two.example.com'], $this->hints($event));
    }

    /**
     * @return list<string>
     */
    private function hints(Event $event): array
    {
        return array_map(static fn (RelayUrl $relay): string => (string) $relay, RelayHintExtractor::extract($event)->toArray());
    }

    private function noteWith(TagCollection $tags, string $content = ''): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            KeyMother::alicePublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString($content),
            $tags,
            Timestamp::fromInt(1700000000),
        ));
    }

    private function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID) ?? throw new RuntimeException('expected a valid event id');
    }

    private function coordinate(): EventCoordinate
    {
        return EventCoordinate::tryFrom(EventKind::fromInt(EventKind::LONGFORM_CONTENT), KeyMother::bob()->getPublicKey(), self::IDENTIFIER)
            ?? throw new RuntimeException('expected a valid coordinate');
    }
}
