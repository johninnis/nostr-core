<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferenceTest extends TestCase
{
    private const EVENT_ID = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

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

    private function eventId(): EventId
    {
        return EventId::fromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');
    }
}
