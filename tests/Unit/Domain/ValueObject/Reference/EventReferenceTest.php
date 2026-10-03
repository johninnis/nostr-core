<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\Service\TagReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferenceTest extends TestCase
{
    private const string EVENT_ID = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const string OTHER_EVENT_ID = '0000000000000000000000000000000000000000000000000000000000000002';
    private const string AUTHOR_HEX = '0000000000000000000000000000000000000000000000000000000000000003';

    public function testIsRootWhenMarkerIsRoot(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Root);

        $this->assertTrue($reference->isRoot());
        $this->assertFalse($reference->isReply());
        $this->assertFalse($reference->isMention());
    }

    public function testIsReplyWhenMarkerIsReply(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Reply);

        $this->assertTrue($reference->isReply());
        $this->assertFalse($reference->isRoot());
        $this->assertFalse($reference->isMention());
    }

    public function testIsMentionWhenMarkerIsMention(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Mention);

        $this->assertTrue($reference->isMention());
        $this->assertFalse($reference->isRoot());
        $this->assertFalse($reference->isReply());
    }

    public function testMarkerPredicatesAreAllFalseWhenMarkerAbsent(): void
    {
        $reference = new EventReference($this->eventId());

        $this->assertFalse($reference->isRoot());
        $this->assertFalse($reference->isReply());
        $this->assertFalse($reference->isMention());
    }

    public function testAMarkerNip10DoesNotDefineIsReadAsNoMarker(): void
    {
        $reference = EventReference::tryFromArray(['event_id' => self::EVENT_ID, 'marker' => 'fork']);

        $this->assertNull($reference?->getMarker());
    }

    public function testEqualsIsTrueForIdenticalReferences(): void
    {
        $a = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply, $this->author());
        $b = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply, $this->author());

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsIsFalseWhenEventIdDiffers(): void
    {
        $a = new EventReference($this->eventId());
        $b = new EventReference($this->otherEventId());

        $this->assertFalse($a->equals($b));
    }

    public function testEqualsIsFalseWhenMarkerDiffers(): void
    {
        $a = new EventReference($this->eventId(), null, Nip10Marker::Reply);
        $b = new EventReference($this->eventId(), null, Nip10Marker::Root);

        $this->assertFalse($a->equals($b));
    }

    public function testEqualsIsFalseWhenRelayDiffers(): void
    {
        $a = new EventReference($this->eventId(), $this->relay('wss://relay.one'));
        $b = new EventReference($this->eventId(), $this->relay('wss://relay.two'));

        $this->assertFalse($a->equals($b));
    }

    public function testEqualsIsFalseWhenOnlyOneSideHasAnAuthor(): void
    {
        $a = new EventReference($this->eventId(), null, null, $this->author());
        $b = new EventReference($this->eventId());

        $this->assertFalse($a->equals($b));
    }

    public function testToArrayFromArrayRoundTripPreservesEveryField(): void
    {
        $reference = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply, $this->author());

        $restored = EventReference::tryFromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertTrue($reference->equals($restored));
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testToArrayFromArrayRoundTripWithOnlyAnEventId(): void
    {
        $reference = new EventReference($this->eventId());

        $restored = EventReference::tryFromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertTrue($reference->equals($restored));
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testTryFromArrayReturnsNullWhenEventIdIsMissingOrNonString(): void
    {
        $this->assertNull(EventReference::tryFromArray(['relay_url' => 'wss://relay.example']));
        $this->assertNull(EventReference::tryFromArray(['event_id' => 123]));
    }

    public function testTryFromArrayReturnsNullWhenEventIdIsNotValidHex(): void
    {
        $this->assertNull(EventReference::tryFromArray(['event_id' => 'not-valid-hex']));
    }

    public function testAMarkedReferenceWritesItsMarkerBeforeItsAuthor(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Root, $this->author());

        $this->assertSame(['e', self::EVENT_ID, '', 'root', self::AUTHOR_HEX], $reference->toETag()->toArray());
    }

    public function testAMarkedReferenceWithoutAnAuthorEndsAtItsMarker(): void
    {
        $reference = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply);

        $this->assertSame(['e', self::EVENT_ID, 'wss://relay.example', 'reply'], $reference->toETag()->toArray());
    }

    public function testAnUnmarkedReferenceWritesItsAuthorAfterAnEmptyMarker(): void
    {
        $reference = new EventReference($this->eventId(), $this->relay(), null, $this->author());

        $this->assertSame(['e', self::EVENT_ID, 'wss://relay.example', '', self::AUTHOR_HEX], $reference->toETag()->toArray());
    }

    public function testAnUnmarkedReferenceWithAnAuthorRoundTripsThroughItsTag(): void
    {
        $reference = new EventReference($this->eventId(), null, null, $this->author());

        $read = TagReferenceExtractor::extract(new TagCollection([$reference->toETag()]))->getEvents()->toArray();

        $this->assertTrue($reference->equals($read[0]));
    }

    private function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');
    }

    private function otherEventId(): EventId
    {
        return EventId::tryFromHex(self::OTHER_EVENT_ID) ?? throw new RuntimeException('Invalid test event id');
    }

    private function author(): PublicKey
    {
        return PublicKey::tryFromHex(self::AUTHOR_HEX) ?? throw new RuntimeException('Invalid test author');
    }

    private function relay(string $url = 'wss://relay.example'): RelayUrl
    {
        return RelayUrl::fromString($url);
    }

    public function testTryFromTagReadsTheIdTheRelayAndTheAuthor(): void
    {
        $reference = EventReference::tryFromTag(Tag::fromArray(['q', self::EVENT_ID, 'wss://relay.example.com', self::AUTHOR_HEX]));

        $this->assertNotNull($reference);
        $this->assertSame(self::EVENT_ID, $reference->getEventId()->toHex());
        $this->assertSame('wss://relay.example.com', (string) $reference->getRelayUrl());
        $this->assertSame(self::AUTHOR_HEX, $reference->getAuthor()?->toHex());
        $this->assertNull($reference->getMarker());
    }

    public function testTryFromTagLeavesAnEmptyAuthorUnstated(): void
    {
        $this->assertNull(EventReference::tryFromTag(Tag::fromArray(['E', self::EVENT_ID, '', '']))?->getAuthor());
    }

    /**
     * @param list<string> $tag
     */
    #[DataProvider('tagsAndTheAuthorTheyName')]
    public function testTryFromTagReadsTheAuthorByTheTagsLength(array $tag, ?string $author): void
    {
        $this->assertSame($author, EventReference::tryFromTag(Tag::fromArray($tag))?->getAuthor()?->toHex());
    }

    /**
     * @return iterable<string, array{list<string>, ?string}>
     */
    public static function tagsAndTheAuthorTheyName(): iterable
    {
        yield 'NIP-10 fifth element after a marker' => [['e', self::EVENT_ID, '', 'reply', self::AUTHOR_HEX], self::AUTHOR_HEX];
        yield 'NIP-10 fifth element after an empty marker' => [['e', self::EVENT_ID, '', '', self::AUTHOR_HEX], self::AUTHOR_HEX];
        yield 'NIP-22 fourth element' => [['e', self::EVENT_ID, '', self::AUTHOR_HEX], self::AUTHOR_HEX];
        yield 'NIP-22 root fourth element' => [['E', self::EVENT_ID, '', self::AUTHOR_HEX], self::AUTHOR_HEX];
        yield 'root tag fourth element beside a fifth' => [['E', self::EVENT_ID, '', self::AUTHOR_HEX, 'extra'], self::AUTHOR_HEX];
        yield 'quote tag fourth element beside a fifth' => [['q', self::EVENT_ID, '', self::AUTHOR_HEX, 'extra'], self::AUTHOR_HEX];
        yield 'fourth element is never read when a fifth exists' => [['e', self::EVENT_ID, '', self::AUTHOR_HEX, 'extra'], null];
        yield 'marker in the fourth element' => [['e', self::EVENT_ID, '', 'root'], null];
        yield 'invalid fifth element' => [['e', self::EVENT_ID, '', 'reply', 'not-a-key'], null];
    }

    public function testTryFromTagReadsTheMarkerOfAnETag(): void
    {
        $this->assertSame(Nip10Marker::Reply, EventReference::tryFromTag(Tag::fromArray(['e', self::EVENT_ID, '', 'reply']))?->getMarker());
    }

    public function testTryFromTagReadsNoMarkerFromAQuoteTag(): void
    {
        $this->assertNull(EventReference::tryFromTag(Tag::fromArray(['q', self::EVENT_ID, '', 'reply']))?->getMarker());
    }

    public function testTryFromTagRefusesATagWithoutAnEventId(): void
    {
        $this->assertNull(EventReference::tryFromTag(Tag::fromArray(['q', 'not-an-id'])));
    }
}
