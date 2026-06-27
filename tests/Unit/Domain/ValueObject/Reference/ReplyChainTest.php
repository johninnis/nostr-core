<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use PHPUnit\Framework\TestCase;

final class ReplyChainTest extends TestCase
{
    private const PUBKEY_A = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const PUBKEY_B = '0000000000000000000000000000000000000000000000000000000000000002';

    public function testParticipantCountReflectsDistinctParticipants(): void
    {
        $chain = new ReplyChain(
            isReply: true,
            isRootPost: false,
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
            isRootPost: true,
            rootEvent: null,
            parentEvent: null,
            conversationParticipants: new PublicKeyCollection(),
            mentionedEvents: new EventReferenceCollection(),
        );

        $this->assertSame(0, $chain->getParticipantCount());
    }
}
