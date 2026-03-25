<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Entity\EventReference;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\Service\TagReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TagCollectionTest extends TestCase
{
    public function testCanCreateEmptyCollection(): void
    {
        $collection = TagCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->toArray());
    }

    public function testCanCreateWithTags(): void
    {
        $tag1 = Tag::event('event-id');
        $tag2 = Tag::pubkey('pubkey-hex');
        $collection = new TagCollection([$tag1, $tag2]);

        $this->assertFalse($collection->isEmpty());
        $this->assertSame(2, $collection->count());
    }

    public function testThrowsExceptionForNonTagItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items must be Tag instances');

        new TagCollection(['not-a-tag']);
    }

    public function testCanAddTag(): void
    {
        $collection = TagCollection::empty();
        $tag = Tag::event('event-id');

        $newCollection = $collection->add($tag);

        $this->assertSame(0, $collection->count());
        $this->assertSame(1, $newCollection->count());
        $this->assertNotSame($collection, $newCollection);
    }

    public function testCanRemoveTagsByType(): void
    {
        $eventTag = Tag::event('event-id');
        $pubkeyTag = Tag::pubkey('pubkey-hex');
        $collection = new TagCollection([$eventTag, $pubkeyTag]);

        $newCollection = $collection->remove(TagType::event());

        $this->assertSame(2, $collection->count());
        $this->assertSame(1, $newCollection->count());
        $this->assertTrue($newCollection->hasType(TagType::pubkey()));
        $this->assertFalse($newCollection->hasType(TagType::event()));
    }

    public function testCanFindByType(): void
    {
        $eventTag1 = Tag::event('event-id-1');
        $eventTag2 = Tag::event('event-id-2');
        $pubkeyTag = Tag::pubkey('pubkey-hex');
        $collection = new TagCollection([$eventTag1, $eventTag2, $pubkeyTag]);

        $eventTags = $collection->findByType(TagType::event());
        $pubkeyTags = $collection->findByType(TagType::pubkey());
        $hashtagTags = $collection->findByType(TagType::hashtag());

        $this->assertCount(2, $eventTags);
        $this->assertCount(1, $pubkeyTags);
        $this->assertCount(0, $hashtagTags);
    }

    public function testHasTypeWorksCorrectly(): void
    {
        $eventTag = Tag::event('event-id');
        $collection = new TagCollection([$eventTag]);

        $this->assertTrue($collection->hasType(TagType::event()));
        $this->assertFalse($collection->hasType(TagType::pubkey()));
    }

    public function testIsIterable(): void
    {
        $tag1 = Tag::event('event-id');
        $tag2 = Tag::pubkey('pubkey-hex');
        $collection = new TagCollection([$tag1, $tag2]);

        $tags = [];
        foreach ($collection as $tag) {
            $tags[] = $tag;
        }

        $this->assertCount(2, $tags);
        $this->assertSame($tag1, $tags[0]);
        $this->assertSame($tag2, $tags[1]);
    }

    public function testToArrayWorksCorrectly(): void
    {
        $tag = Tag::event('event-id', 'relay-url');
        $collection = new TagCollection([$tag]);

        $expected = [['e', 'event-id', 'relay-url']];
        $this->assertSame($expected, $collection->toArray());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $tag1 = Tag::event('event-id');
        $tag2 = Tag::pubkey('pubkey-hex');

        $collection1 = new TagCollection([$tag1, $tag2]);
        $collection2 = new TagCollection([$tag1, $tag2]);
        $collection3 = new TagCollection([$tag1]);
        $collection4 = new TagCollection([$tag2, $tag1]);

        $this->assertTrue($collection1->equals($collection2));
        $this->assertFalse($collection1->equals($collection3));
        $this->assertFalse($collection1->equals($collection4));
    }

    public function testFromArrayWorksCorrectly(): void
    {
        $data = [
            ['e', 'event-id'],
            ['p', 'pubkey-hex'],
        ];

        $collection = TagCollection::fromArray($data);

        $this->assertSame(2, $collection->count());
        $this->assertTrue($collection->hasType(TagType::event()));
        $this->assertTrue($collection->hasType(TagType::pubkey()));
    }

    public function testExtractReferencesExtractsEventTags(): void
    {
        $tags = TagCollection::fromArray([
            ['e', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'reply', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['e', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', '', 'root'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $events = $references->getEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(EventReference::class, $events[0]);
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $events[0]->getEventId()->toHex());
        $this->assertEquals('wss://relay.com', (string) $events[0]->getRelayUrl());
        $this->assertEquals('reply', $events[0]->getMarker());
        $this->assertNotNull($events[0]->getAuthor());
        $this->assertEquals('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $events[0]->getAuthor()->toHex());
        $this->assertEquals('cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', $events[1]->getEventId()->toHex());
        $this->assertNull($events[1]->getRelayUrl());
        $this->assertEquals('root', $events[1]->getMarker());
        $this->assertNull($events[1]->getAuthor());
    }

    public function testExtractReferencesExtractsPubkeyTags(): void
    {
        $tags = TagCollection::fromArray([
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'alice'],
            ['p', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $pubkeys = $references->getPubkeys();
        $this->assertCount(2, $pubkeys);
        $this->assertInstanceOf(PubkeyReference::class, $pubkeys[0]);
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $pubkeys[0]->getPubkey()->toHex());
        $this->assertEquals('wss://relay.com', (string) $pubkeys[0]->getRelayUrl());
        $this->assertEquals('alice', $pubkeys[0]->getPetname());
        $this->assertEquals('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $pubkeys[1]->getPubkey()->toHex());
        $this->assertNull($pubkeys[1]->getRelayUrl());
        $this->assertNull($pubkeys[1]->getPetname());
    }

    public function testExtractReferencesExtractsQuoteTags(): void
    {
        $tags = TagCollection::fromArray([
            ['q', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['q', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $quotes = $references->getQuotes();
        $this->assertCount(2, $quotes);
        $this->assertInstanceOf(EventReference::class, $quotes[0]);
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $quotes[0]->getEventId()->toHex());
        $this->assertEquals('wss://relay.com', (string) $quotes[0]->getRelayUrl());
        $this->assertNotNull($quotes[0]->getAuthor());
        $this->assertEquals('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $quotes[0]->getAuthor()->toHex());
        $this->assertEquals('cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', $quotes[1]->getEventId()->toHex());
        $this->assertNull($quotes[1]->getRelayUrl());
        $this->assertNull($quotes[1]->getAuthor());
    }

    public function testExtractReferencesExtractsAddressableTags(): void
    {
        $pubkey1 = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $pubkey2 = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

        $tags = TagCollection::fromArray([
            ['a', "30023:{$pubkey1}:my-article", 'wss://relay.com'],
            ['a', "30001:{$pubkey2}:bookmark-list"],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $addressable = $references->getAddressable();
        $this->assertCount(2, $addressable);
        $this->assertEquals(30023, $addressable[0]->getKind()->toInt());
        $this->assertEquals($pubkey1, $addressable[0]->getPubkey()->toHex());
        $this->assertEquals('my-article', $addressable[0]->getIdentifier());
        $this->assertEquals('wss://relay.com', (string) $addressable[0]->getRelayHint());
        $this->assertEquals(30001, $addressable[1]->getKind()->toInt());
        $this->assertEquals($pubkey2, $addressable[1]->getPubkey()->toHex());
        $this->assertEquals('bookmark-list', $addressable[1]->getIdentifier());
        $this->assertNull($addressable[1]->getRelayHint());
    }

    public function testExtractReferencesIgnoresInvalidEventIds(): void
    {
        $tags = TagCollection::fromArray([
            ['e', 'invalid_hex'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $this->assertCount(1, $references->getPubkeys());
        $this->assertEmpty($references->getEvents());
    }

    public function testExtractReferencesIgnoresInvalidAddressableTags(): void
    {
        $validPubkey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        $tags = TagCollection::fromArray([
            ['a', 'invalid_format'],
            ['a', 'only_one_part'],
            ['a', '1:invalidpubkey:identifier'],
            ['a', '30023:badpubkey:identifier'],
            ['a', "30023:{$validPubkey}:"],
            ['a', "30023:{$validPubkey}:my-article"],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $addressable = $references->getAddressable();
        $this->assertCount(1, $addressable);
        $this->assertEquals(30023, $addressable[0]->getKind()->toInt());
    }

    public function testExtractReferencesReturnsEmptyForUnknownTags(): void
    {
        $tags = TagCollection::fromArray([
            ['unknown', 'tag'],
            ['other', 'value'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $this->assertEmpty($references->getEvents());
        $this->assertEmpty($references->getPubkeys());
        $this->assertEmpty($references->getQuotes());
        $this->assertEmpty($references->getAddressable());
        $this->assertEmpty($references->getRelays());
        $this->assertEmpty($references->getChallenges());
    }

    public function testExtractReferencesExtractsRelayTags(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'wss://relay.com', 'read'],
            ['r', 'wss://other.com'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $relays = $references->getRelays();
        $this->assertCount(2, $relays);
        $this->assertInstanceOf(RelayReference::class, $relays[0]);
        $this->assertEquals('wss://relay.com', (string) $relays[0]->getRelayUrl());
        $this->assertEquals('read', $relays[0]->getMode());
        $this->assertEquals('wss://other.com', (string) $relays[1]->getRelayUrl());
        $this->assertNull($relays[1]->getMode());
    }

    public function testExtractReferencesExtractsChallengeTags(): void
    {
        $tags = TagCollection::fromArray([
            ['challenge', 'abc123'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $this->assertCount(1, $references->getChallenges());
        $this->assertEquals('abc123', $references->getChallenges()[0]);
    }

    public function testAnalyseReplyChainForRootPost(): void
    {
        $tags = TagCollection::fromArray([
            ['p', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'],
            ['subject', 'Hello World'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertFalse($replyChain->isReply());
        $this->assertTrue($replyChain->isRootPost());
        $this->assertNull($replyChain->getRootEvent());
        $this->assertNull($replyChain->getParentEvent());
        $this->assertCount(1, $replyChain->getConversationParticipants());
        $this->assertEmpty($replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainWithMarkers(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'wss://relay.com', 'root'],
            ['e', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', 'wss://relay.com', 'reply'],
            ['p', 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertFalse($replyChain->isRootPost());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertEquals('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $replyChain->getRootEvent()->getEventId()->toHex());
        $this->assertEquals('root', $replyChain->getRootEvent()->getMarker());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertEquals('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', $replyChain->getParentEvent()->getEventId()->toHex());
        $this->assertEquals('reply', $replyChain->getParentEvent()->getMarker());
        $this->assertCount(1, $replyChain->getConversationParticipants());
        $this->assertEmpty($replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainSingleEventReply(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'wss://relay.com'],
            ['p', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertFalse($replyChain->isRootPost());
        $this->assertNull($replyChain->getRootEvent());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertEquals('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $replyChain->getParentEvent()->getEventId()->toHex());
    }

    public function testAnalyseReplyChainMultipleEventsWithoutMarkers(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay1.com'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay2.com'],
            ['e', '3333333333333333333333333333333333333333333333333333333333333333', 'wss://relay3.com'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['p', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', $replyChain->getRootEvent()->getEventId()->toHex());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertEquals('3333333333333333333333333333333333333333333333333333333333333333', $replyChain->getParentEvent()->getEventId()->toHex());
        $this->assertCount(1, $replyChain->getMentionedEvents());
        $this->assertEquals('2222222222222222222222222222222222222222222222222222222222222222', $replyChain->getMentionedEvents()[0]->getEventId()->toHex());
        $this->assertCount(2, $replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainWithMentions(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', '', 'root'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', '', 'mention'],
            ['e', '3333333333333333333333333333333333333333333333333333333333333333'],
            ['e', '4444444444444444444444444444444444444444444444444444444444444444', '', 'reply'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', $replyChain->getRootEvent()->getEventId()->toHex());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertEquals('4444444444444444444444444444444444444444444444444444444444444444', $replyChain->getParentEvent()->getEventId()->toHex());
        $this->assertCount(2, $replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainWithAuthor(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'reply', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['p', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $parentEvent = $replyChain->getParentEvent();
        $this->assertNotNull($parentEvent);
        $this->assertEquals('wss://relay.com', (string) $parentEvent->getRelayUrl());
        $this->assertEquals('reply', $parentEvent->getMarker());
        $this->assertNotNull($parentEvent->getAuthor());
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $parentEvent->getAuthor()->toHex());
    }

    public function testAnalyseReplyChainSkipsInvalidEventIds(): void
    {
        $tags = TagCollection::fromArray([
            ['e', 'invalid_hex', 'wss://relay.com'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertNull($replyChain->getRootEvent());
        $this->assertNull($replyChain->getParentEvent());
    }

    public function testAnalyseReplyChainHandlesInvalidPubkeys(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'],
            ['p', 'invalid_pubkey_format'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertEmpty($replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainHandlesInvalidRelayUrls(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'invalid-url'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertNull($replyChain->getParentEvent()->getRelayUrl());
    }

    public function testAnalyseReplyChainNullKindUsesNip10Logic(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'wss://relay.com', 'root'],
            ['e', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', 'wss://relay.com', 'reply'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, null);

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertSame('root', $replyChain->getRootEvent()->getMarker());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertSame('reply', $replyChain->getParentEvent()->getMarker());
    }

    public function testAnalyseReplyChainCommentWithRootAndParent(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $parentId = '2222222222222222222222222222222222222222222222222222222222222222';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $parentAuthor = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $participant = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

        $tags = TagCollection::fromArray([
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['e', $parentId, 'wss://relay.com', $parentAuthor],
            ['p', $participant],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $this->assertTrue($replyChain->isReply());
        $this->assertFalse($replyChain->isRootPost());

        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertSame($rootId, $replyChain->getRootEvent()->getEventId()->toHex());
        $this->assertNull($replyChain->getRootEvent()->getMarker());
        $this->assertNotNull($replyChain->getRootEvent()->getAuthor());
        $this->assertSame($rootAuthor, $replyChain->getRootEvent()->getAuthor()->toHex());

        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertSame($parentId, $replyChain->getParentEvent()->getEventId()->toHex());
        $this->assertNull($replyChain->getParentEvent()->getMarker());
        $this->assertNotNull($replyChain->getParentEvent()->getAuthor());
        $this->assertSame($parentAuthor, $replyChain->getParentEvent()->getAuthor()->toHex());

        $this->assertCount(1, $replyChain->getConversationParticipants());
        $this->assertEmpty($replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainCommentWithOnlyRootTag(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $tags = TagCollection::fromArray([
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $this->assertTrue($replyChain->isReply());
        $this->assertFalse($replyChain->isRootPost());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertNull($replyChain->getParentEvent());
    }

    public function testAnalyseReplyChainCommentWithOnlyParentTag(): void
    {
        $parentId = '2222222222222222222222222222222222222222222222222222222222222222';
        $parentAuthor = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        $tags = TagCollection::fromArray([
            ['e', $parentId, 'wss://relay.com', $parentAuthor],
            ['K', 'web'],
            ['k', '1111'],
            ['I', 'https://example.com'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $this->assertTrue($replyChain->isReply());
        $this->assertNull($replyChain->getRootEvent());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertSame($parentId, $replyChain->getParentEvent()->getEventId()->toHex());
    }

    public function testAnalyseReplyChainCommentPosition3IsPubkeyNotMarker(): void
    {
        $eventId = '1111111111111111111111111111111111111111111111111111111111111111';
        $authorPubkey = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $tags = TagCollection::fromArray([
            ['e', $eventId, 'wss://relay.com', $authorPubkey],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $parentEvent = $replyChain->getParentEvent();
        $this->assertNotNull($parentEvent);
        $this->assertNull($parentEvent->getMarker());
        $this->assertNotNull($parentEvent->getAuthor());
        $this->assertSame($authorPubkey, $parentEvent->getAuthor()->toHex());
    }

    public function testAnalyseReplyChainCommentGracefullySkipsInvalidIds(): void
    {
        $tags = TagCollection::fromArray([
            ['E', 'invalid_hex', 'wss://relay.com', 'also_invalid'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay.com', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['p', 'invalid_pubkey'],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $this->assertNull($replyChain->getRootEvent());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertSame('2222222222222222222222222222222222222222222222222222222222222222', $replyChain->getParentEvent()->getEventId()->toHex());
        $this->assertEmpty($replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainKind1StillUsesNip10Logic(): void
    {
        $tags = TagCollection::fromArray([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'root'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay.com', 'reply'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::textNote());

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertSame('root', $replyChain->getRootEvent()->getMarker());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertSame('reply', $replyChain->getParentEvent()->getMarker());
    }

    public function testAnalyseReplyChainCommentWithOnlyRootTagIsReply(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $tags = TagCollection::fromArray([
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['p', $rootAuthor],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $this->assertTrue($replyChain->isReply());
        $this->assertFalse($replyChain->isRootPost());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertSame($rootId, $replyChain->getRootEvent()->getEventId()->toHex());
        $this->assertNull($replyChain->getParentEvent());
        $this->assertCount(1, $replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainCommentWithNoEventTagsIsRootPost(): void
    {
        $tags = TagCollection::fromArray([
            ['I', 'https://example.com'],
            ['K', 'web'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::comment());

        $this->assertFalse($replyChain->isReply());
        $this->assertTrue($replyChain->isRootPost());
        $this->assertNull($replyChain->getRootEvent());
        $this->assertNull($replyChain->getParentEvent());
    }
}
