<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use PHPUnit\Framework\TestCase;

final class ReplyChainAnalyserTest extends TestCase
{
    private const string ROOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string PARENT_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string MENTION_ID = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
    private const string ROOT_AUTHOR = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
    private const string PARENT_AUTHOR = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    public function testNip10MarkedChainResolvesRootReplyAndMention(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::ROOT_ID, 'wss://relay.example', 'root']),
            Tag::fromArray(['e', self::PARENT_ID, 'wss://relay.example', 'reply']),
            Tag::fromArray(['e', self::MENTION_ID, 'wss://relay.example', 'mention']),
            Tag::fromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertTrue($chain->isReply());
        $this->assertFalse($chain->isRootPost());
        $this->assertSame(self::ROOT_ID, $chain->getRootEvent()?->getEventId()->toHex());
        $this->assertSame(self::PARENT_ID, $chain->getParentEvent()?->getEventId()->toHex());
        $this->assertSame(1, $chain->getMentionedEventCount());
        $this->assertSame(self::PARENT_AUTHOR, $chain->getConversationParticipants()->toArray()[0]->toHex());
    }

    public function testNip10PositionalChainUsesFirstAsRootAndLastAsParent(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::ROOT_ID]),
            Tag::fromArray(['e', self::MENTION_ID]),
            Tag::fromArray(['e', self::PARENT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertSame(self::ROOT_ID, $chain->getRootEvent()?->getEventId()->toHex());
        $this->assertSame(self::PARENT_ID, $chain->getParentEvent()?->getEventId()->toHex());
        $this->assertSame(1, $chain->getMentionedEventCount());
    }

    public function testNip10SingleEventTagIsTreatedAsParent(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::PARENT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($chain->hasRoot());
        $this->assertSame(self::PARENT_ID, $chain->getParentEvent()?->getEventId()->toHex());
    }

    public function testEventWithoutEventTagsIsARootPost(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($chain->isReply());
        $this->assertTrue($chain->isRootPost());
        $this->assertFalse($chain->hasRoot());
        $this->assertFalse($chain->hasParent());
    }

    public function testNip22CommentCollectsBothRootAndParentAuthors(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['E', self::ROOT_ID, 'wss://relay.example', self::ROOT_AUTHOR]),
            Tag::fromArray(['K', '1']),
            Tag::fromArray(['P', self::ROOT_AUTHOR, 'wss://relay.example']),
            Tag::fromArray(['e', self::PARENT_ID, 'wss://relay.example', self::PARENT_AUTHOR]),
            Tag::fromArray(['k', '1111']),
            Tag::fromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $rootEvent = $chain->getRootEvent();
        $parentEvent = $chain->getParentEvent();
        $this->assertNotNull($rootEvent);
        $this->assertNotNull($parentEvent);

        $this->assertTrue($chain->isReply());
        $this->assertSame(self::ROOT_ID, $rootEvent->getEventId()->toHex());
        $this->assertSame(self::ROOT_AUTHOR, $rootEvent->getAuthor()?->toHex());
        $this->assertSame(self::PARENT_ID, $parentEvent->getEventId()->toHex());
        $this->assertSame(self::PARENT_AUTHOR, $parentEvent->getAuthor()?->toHex());

        $participants = array_map(
            static fn (PublicKey $pubkey): string => $pubkey->toHex(),
            $chain->getConversationParticipants()->toArray(),
        );
        $this->assertContains(self::ROOT_AUTHOR, $participants, 'NIP-22 root author (P tag) must be a participant');
        $this->assertContains(self::PARENT_AUTHOR, $participants, 'NIP-22 parent author (p tag) must be a participant');
    }

    public function testNip22CommentRootedOnAddressableEventDoesNotYetResolveARoot(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['A', '30023:'.self::ROOT_AUTHOR.':my-article', 'wss://relay.example']),
            Tag::fromArray(['K', '30023']),
            Tag::fromArray(['P', self::ROOT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertFalse($chain->hasRoot(), 'Known limitation: EventReference holds an EventId, so addressable (A) roots are not resolved');
        $this->assertFalse($chain->isReply());
        $this->assertSame(self::ROOT_AUTHOR, $chain->getConversationParticipants()->toArray()[0]->toHex());
    }
}
