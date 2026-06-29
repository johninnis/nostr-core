<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplyChainTest extends TestCase
{
    private const PUBKEY_A = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const PUBKEY_B = '0000000000000000000000000000000000000000000000000000000000000002';
    private const EVENT_ROOT = '1111111111111111111111111111111111111111111111111111111111111111';
    private const EVENT_PARENT = '2222222222222222222222222222222222222222222222222222222222222222';
    private const EVENT_MENTION = '3333333333333333333333333333333333333333333333333333333333333333';

    public function testParticipantCountReflectsDistinctParticipants(): void
    {
        $chain = new ReplyChain(
            isReply: true,
            rootEvent: null,
            parentEvent: null,
            conversationParticipants: PublicKeyCollection::fromHexValues([self::PUBKEY_A, self::PUBKEY_B]),
            mentionedEvents: new EventReferenceCollection(),
        );

        $this->assertSame(2, $chain->getParticipantCount());
    }

    public function testParticipantCountIsZeroWithoutParticipants(): void
    {
        $chain = new ReplyChain(
            isReply: false,
            rootEvent: null,
            parentEvent: null,
            conversationParticipants: new PublicKeyCollection(),
            mentionedEvents: new EventReferenceCollection(),
        );

        $this->assertSame(0, $chain->getParticipantCount());
    }

    public function testToArrayFromArrayRoundTripWithRootParentAndMentions(): void
    {
        $chain = new ReplyChain(
            isReply: true,
            rootEvent: new EventReference($this->eventId(self::EVENT_ROOT), null, Nip10Marker::Root->value),
            parentEvent: new EventReference($this->eventId(self::EVENT_PARENT), null, Nip10Marker::Reply->value),
            conversationParticipants: PublicKeyCollection::fromHexValues([self::PUBKEY_A, self::PUBKEY_B]),
            mentionedEvents: new EventReferenceCollection([
                new EventReference($this->eventId(self::EVENT_MENTION), null, Nip10Marker::Mention->value),
            ]),
        );

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
    }

    public function testToArrayFromArrayRoundTripWithEmptyChain(): void
    {
        $chain = new ReplyChain(false, null, null, new PublicKeyCollection(), new EventReferenceCollection());

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
        $this->assertTrue($restored->isRootPost());
    }

    private function eventId(string $hex): EventId
    {
        return EventId::fromHex($hex) ?? throw new RuntimeException('Invalid test event id');
    }
}
