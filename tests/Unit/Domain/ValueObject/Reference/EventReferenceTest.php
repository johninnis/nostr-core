<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferenceTest extends TestCase
{
    private const EVENT_ID = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const OTHER_EVENT_ID = '0000000000000000000000000000000000000000000000000000000000000002';
    private const AUTHOR_HEX = '0000000000000000000000000000000000000000000000000000000000000003';

    public function testIsRootWhenMarkerIsRoot(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Root->value);

        $this->assertTrue($reference->isRoot());
        $this->assertFalse($reference->isReply());
        $this->assertFalse($reference->isMention());
    }

    public function testIsReplyWhenMarkerIsReply(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Reply->value);

        $this->assertTrue($reference->isReply());
        $this->assertFalse($reference->isRoot());
        $this->assertFalse($reference->isMention());
    }

    public function testIsMentionWhenMarkerIsMention(): void
    {
        $reference = new EventReference($this->eventId(), null, Nip10Marker::Mention->value);

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

    public function testEqualsIsTrueForIdenticalReferences(): void
    {
        $a = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply->value, $this->author());
        $b = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply->value, $this->author());

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
        $a = new EventReference($this->eventId(), null, Nip10Marker::Reply->value);
        $b = new EventReference($this->eventId(), null, Nip10Marker::Root->value);

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
        $reference = new EventReference($this->eventId(), $this->relay(), Nip10Marker::Reply->value, $this->author());

        $restored = EventReference::fromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertTrue($reference->equals($restored));
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testToArrayFromArrayRoundTripWithOnlyAnEventId(): void
    {
        $reference = new EventReference($this->eventId());

        $restored = EventReference::fromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertTrue($reference->equals($restored));
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testFromArrayReturnsNullWhenEventIdIsMissingOrNonString(): void
    {
        $this->assertNull(EventReference::fromArray(['relay_url' => 'wss://relay.example']));
        $this->assertNull(EventReference::fromArray(['event_id' => 123]));
    }

    public function testFromArrayReturnsNullWhenEventIdIsNotValidHex(): void
    {
        $this->assertNull(EventReference::fromArray(['event_id' => 'not-valid-hex']));
    }

    private function eventId(): EventId
    {
        return EventId::fromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');
    }

    private function otherEventId(): EventId
    {
        return EventId::fromHex(self::OTHER_EVENT_ID) ?? throw new RuntimeException('Invalid test event id');
    }

    private function author(): PublicKey
    {
        return PublicKey::fromHex(self::AUTHOR_HEX) ?? throw new RuntimeException('Invalid test author');
    }

    private function relay(string $url = 'wss://relay.example'): RelayUrl
    {
        return RelayUrl::fromString($url) ?? throw new RuntimeException('Invalid test relay url');
    }
}
