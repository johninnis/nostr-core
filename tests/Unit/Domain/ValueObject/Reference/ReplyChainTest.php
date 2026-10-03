<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplyChainTest extends TestCase
{
    private const string PUBKEY_A = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const string PUBKEY_B = '0000000000000000000000000000000000000000000000000000000000000002';
    private const string EVENT_ROOT = '1111111111111111111111111111111111111111111111111111111111111111';
    private const string EVENT_PARENT = '2222222222222222222222222222222222222222222222222222222222222222';
    private const string EVENT_MENTION = '3333333333333333333333333333333333333333333333333333333333333333';

    public function testParticipantCountReflectsDistinctParticipants(): void
    {
        $chain = new ReplyChain(
            kind: self::kind(EventKind::TEXT_NOTE),
            root: null,
            parent: null,
            conversationParticipants: PublicKeyCollection::fromHexValues([self::PUBKEY_A, self::PUBKEY_B]),
            mentionedEvents: new EventReferenceCollection(),
        );

        $this->assertSame(2, $chain->getParticipantCount());
    }

    public function testParticipantCountIsZeroWithoutParticipants(): void
    {
        $chain = new ReplyChain(self::kind(EventKind::REACTION), null, null, new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertSame(0, $chain->getParticipantCount());
    }

    public function testToArrayFromArrayRoundTripWithRootParentAndMentions(): void
    {
        $chain = new ReplyChain(
            kind: self::kind(EventKind::TEXT_NOTE),
            root: new EventReference($this->eventId(self::EVENT_ROOT), null, Nip10Marker::Root),
            parent: new EventReference($this->eventId(self::EVENT_PARENT), null, Nip10Marker::Reply),
            conversationParticipants: PublicKeyCollection::fromHexValues([self::PUBKEY_A, self::PUBKEY_B]),
            mentionedEvents: new EventReferenceCollection([
                new EventReference($this->eventId(self::EVENT_MENTION), null, Nip10Marker::Mention),
            ]),
        );

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
    }

    public function testToArrayFromArrayRoundTripWithEmptyChain(): void
    {
        $chain = new ReplyChain(self::kind(EventKind::REACTION), null, null, new PublicKeyCollection(), new EventReferenceCollection());

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
        $this->assertFalse($restored->isReply());
    }

    public function testToArrayFromArrayRoundTripWithoutAKind(): void
    {
        $chain = new ReplyChain(null, null, new EventReference($this->eventId(self::EVENT_PARENT)), new PublicKeyCollection(), new EventReferenceCollection());

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
        $this->assertTrue($restored->isReply());
    }

    public function testAStoredReplyFlagWithNothingToReplyToIsNotAReply(): void
    {
        $this->assertFalse(ReplyChain::fromArray(['is_reply' => true, 'kind' => EventKind::TEXT_NOTE])->isReply());
    }

    public function testAShortNoteThatResolvesAParentIsAReply(): void
    {
        $chain = new ReplyChain(self::kind(EventKind::TEXT_NOTE), null, new EventReference($this->eventId(self::EVENT_PARENT)), new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertTrue($chain->isReply());
    }

    public function testTagsReadWithoutAKindThatResolveAParentAreAReply(): void
    {
        $chain = new ReplyChain(null, null, new EventReference($this->eventId(self::EVENT_PARENT)), new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertTrue($chain->isReply());
    }

    #[DataProvider('kindsThatDoNotThread')]
    public function testAChainWhoseKindDoesNotThreadIsNotAReplyEvenWithAParent(int $kind): void
    {
        $chain = new ReplyChain(self::kind($kind), null, new EventReference($this->eventId(self::EVENT_PARENT)), new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertFalse($chain->isReply());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function kindsThatDoNotThread(): iterable
    {
        yield 'a reaction' => [EventKind::REACTION];
        yield 'a repost' => [EventKind::REPOST];
        yield 'a long-form article' => [EventKind::LONGFORM_CONTENT];
    }

    public function testToArrayFromArrayRoundTripWithRootAndParentAddresses(): void
    {
        $chain = new ReplyChain(
            kind: self::kind(EventKind::COMMENT),
            root: $this->address('30023:'.self::PUBKEY_A.':root', 'wss://relay.example'),
            parent: $this->address('30023:'.self::PUBKEY_B.':parent'),
            conversationParticipants: new PublicKeyCollection(),
            mentionedEvents: new EventReferenceCollection(),
        );

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
        $this->assertTrue($restored->isReply());
        $this->assertInstanceOf(EventCoordinate::class, $restored->getRoot());
    }

    public function testACommentThatResolvesOnlyAnAddressRootIsAReply(): void
    {
        $chain = new ReplyChain(self::kind(EventKind::COMMENT), $this->address('30023:'.self::PUBKEY_A.':root'), null, new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertTrue($chain->hasRoot());
        $this->assertTrue($chain->isReply());
    }

    public function testACommentThatResolvesOnlyAnAddressParentIsAReply(): void
    {
        $chain = new ReplyChain(self::kind(EventKind::COMMENT), null, $this->address('30023:'.self::PUBKEY_A.':parent'), new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertTrue($chain->hasParent());
        $this->assertTrue($chain->isReply());
    }

    public function testToArrayFromArrayRoundTripWithRootAndParentExternalIdentifiers(): void
    {
        $chain = new ReplyChain(
            kind: self::kind(EventKind::COMMENT),
            root: $this->external('podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f', 'podcast:item:guid', 'https://fountain.fm/episode/z1y9TMQRuqXl2awyrQxg'),
            parent: $this->external('podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f', 'podcast:item:guid'),
            conversationParticipants: new PublicKeyCollection(),
            mentionedEvents: new EventReferenceCollection(),
        );

        $restored = ReplyChain::fromArray($chain->toArray());

        $this->assertSame($chain->toArray(), $restored->toArray());
        $this->assertTrue($restored->isReply());
        $this->assertInstanceOf(ExternalContentId::class, $restored->getParent());
        $this->assertSame('podcast:item:guid', $restored->getParent()->getKind());
    }

    public function testACommentThatResolvesOnlyAnExternalRootIsAReply(): void
    {
        $chain = new ReplyChain(self::kind(EventKind::COMMENT), $this->external('isbn:9780765382030', 'isbn'), null, new PublicKeyCollection(), new EventReferenceCollection());

        $this->assertTrue($chain->hasRoot());
        $this->assertTrue($chain->isReply());
    }

    public function testAStoredReferenceOfNoKnownTypeIsNoReference(): void
    {
        $chain = ReplyChain::fromArray(['kind' => EventKind::COMMENT, 'root' => ['type' => 'unknown', 'value' => 'x']]);

        $this->assertNull($chain->getRoot());
        $this->assertFalse($chain->isReply());
    }

    private static function kind(int $kind): EventKind
    {
        return EventKind::fromInt($kind);
    }

    private function external(string $value, string $kind, ?string $hint = null): ExternalContentId
    {
        return ExternalContentId::tryFromString($value, $kind, $hint) ?? throw new RuntimeException('Invalid test external content id');
    }

    private function address(string $coordinate, ?string $relayHint = null): EventCoordinate
    {
        return EventCoordinate::tryFromString($coordinate, $relayHint) ?? throw new RuntimeException('Invalid test coordinate');
    }

    private function eventId(string $hex): EventId
    {
        return EventId::tryFromHex($hex) ?? throw new RuntimeException('Invalid test event id');
    }
}
