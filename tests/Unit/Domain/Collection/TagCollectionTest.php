<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\Enum\RelayMarker;
use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\Service\TagReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagCollectionTest extends TestCase
{
    public function testCanCreateEmptyCollection(): void
    {
        $collection = new TagCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->toArray());
    }

    public function testCanCreateWithTags(): void
    {
        $tag1 = Tag::fromArray(['e', 'event-id']);
        $tag2 = Tag::fromArray(['p', 'pubkey-hex']);
        $collection = new TagCollection([$tag1, $tag2]);

        $this->assertFalse($collection->isEmpty());
        $this->assertSame(2, $collection->count());
    }

    public function testThrowsExceptionForNonTagItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items must be '.Tag::class.' instances');

        new TagCollection(['not-a-tag']);
    }

    public function testAddAddsATagOfANewTypeAndValue(): void
    {
        $collection = new TagCollection();
        $tag = Tag::fromArray(['e', 'event-id']);

        $newCollection = $collection->add($tag);

        $this->assertSame(0, $collection->count());
        $this->assertSame(1, $newCollection->count());
        $this->assertNotSame($collection, $newCollection);
    }

    public function testAddReplacesTheTagWithTheSameTypeAndValueAtTheEnd(): void
    {
        $collection = new TagCollection([Tag::fromArray(['e', 'event-id', 'wss://old.example']), Tag::fromArray(['p', 'pubkey-hex'])]);

        $added = $collection->add(Tag::fromArray(['e', 'event-id', 'wss://new.example']));

        $this->assertSame([['p', 'pubkey-hex'], ['e', 'event-id', 'wss://new.example']], $added->toJsonArray());
    }

    public function testRemoveDeletesOnlyTheTagMatchingTypeAndValue(): void
    {
        $eventTag1 = Tag::fromArray(['e', 'event-id-1']);
        $eventTag2 = Tag::fromArray(['e', 'event-id-2']);
        $collection = new TagCollection([$eventTag1, $eventTag2, Tag::fromArray(['p', 'pubkey-hex'])]);

        $remainingEvents = $collection->remove($eventTag1)->findByType(TagType::event());

        $this->assertCount(1, $remainingEvents);
        $this->assertSame('event-id-2', $remainingEvents[0]->getValue());
    }

    public function testGetPubkeysReturnsPublicKeysFromPubkeyTags(): void
    {
        $collection = new TagCollection([Tag::fromArray(['p', str_repeat('a', 64)]), Tag::fromArray(['e', 'not-a-pubkey'])]);

        $this->assertCount(1, $collection->getPubkeys());
    }

    public function testGetEventIdsReturnsEventIdsFromEventTags(): void
    {
        $collection = new TagCollection([Tag::fromArray(['e', str_repeat('b', 64)]), Tag::fromArray(['p', str_repeat('a', 64)])]);

        $this->assertCount(1, $collection->getEventIds());
    }

    public function testGetCoordinatesReadsTheCoordinateAndRelayHintOfAnATag(): void
    {
        $pubkey = str_repeat('a', 64);
        $collection = new TagCollection([Tag::fromArray(['a', "30023:{$pubkey}:my-article", 'wss://relay.com'])]);

        $coordinate = $collection->getCoordinates()->toArray()[0];

        $this->assertSame("30023:{$pubkey}:my-article", (string) $coordinate);
        $this->assertSame('wss://relay.com', (string) $coordinate->getRelayHint());
    }

    public function testGetCoordinatesIgnoresACoordinateInAQTag(): void
    {
        $collection = new TagCollection([Tag::fromArray(['q', '30023:'.str_repeat('a', 64).':my-article'])]);

        $this->assertTrue($collection->getCoordinates()->isEmpty());
    }

    public function testGetCoordinatesSkipsAnATagThatNamesNoCoordinate(): void
    {
        $collection = new TagCollection([Tag::fromArray(['a', 'not-a-coordinate'])]);

        $this->assertTrue($collection->getCoordinates()->isEmpty());
    }

    public function testCanFindByType(): void
    {
        $eventTag1 = Tag::fromArray(['e', 'event-id-1']);
        $eventTag2 = Tag::fromArray(['e', 'event-id-2']);
        $pubkeyTag = Tag::fromArray(['p', 'pubkey-hex']);
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
        $eventTag = Tag::fromArray(['e', 'event-id']);
        $collection = new TagCollection([$eventTag]);

        $this->assertTrue($collection->hasType(TagType::event()));
        $this->assertFalse($collection->hasType(TagType::pubkey()));
    }

    public function testIsIterable(): void
    {
        $tag1 = Tag::fromArray(['e', 'event-id']);
        $tag2 = Tag::fromArray(['p', 'pubkey-hex']);
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
        $tag = Tag::fromArray(['e', 'event-id', 'relay-url']);
        $collection = new TagCollection([$tag]);

        $expected = [['e', 'event-id', 'relay-url']];
        $this->assertSame($expected, $collection->toJsonArray());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $tag1 = Tag::fromArray(['e', 'event-id']);
        $tag2 = Tag::fromArray(['p', 'pubkey-hex']);

        $collection1 = new TagCollection([$tag1, $tag2]);
        $collection2 = new TagCollection([$tag1, $tag2]);
        $collection3 = new TagCollection([$tag1]);
        $collection4 = new TagCollection([$tag2, $tag1]);

        $this->assertTrue($collection1->equals($collection2));
        $this->assertFalse($collection1->equals($collection3));
        $this->assertFalse($collection1->equals($collection4));
    }

    public function testTryFromArrayWorksCorrectly(): void
    {
        $data = [
            ['e', 'event-id'],
            ['p', 'pubkey-hex'],
        ];

        $collection = TagCollection::tryFromArray($data);

        $this->assertNotNull($collection);
        $this->assertSame(2, $collection->count());
        $this->assertTrue($collection->hasType(TagType::event()));
        $this->assertTrue($collection->hasType(TagType::pubkey()));
    }

    public function testTryFromArrayReturnsNullWhenAnElementIsNotAnArray(): void
    {
        $this->assertNull(TagCollection::tryFromArray([['e', 'event-id'], 'not-an-array']));
    }

    public function testTryFromArrayReturnsNullWhenATagIsMalformed(): void
    {
        $this->assertNull(TagCollection::tryFromArray([[]]));
    }

    public function testTryFromArrayReturnsNullWhenGivenANonArray(): void
    {
        $this->assertNull(TagCollection::tryFromArray('not even an array'));
    }

    public function testExtractReferencesExtractsEventTags(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'reply', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['e', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', '', 'root'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $events = $references->getEvents()->toArray();
        $this->assertCount(2, $events);
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $events[0]->getEventId()->toHex());
        $this->assertEquals('wss://relay.com', (string) $events[0]->getRelayUrl());
        $this->assertSame(Nip10Marker::Reply, $events[0]->getMarker());
        $this->assertNotNull($events[0]->getAuthor());
        $this->assertEquals('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $events[0]->getAuthor()->toHex());
        $this->assertEquals('cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', $events[1]->getEventId()->toHex());
        $this->assertNull($events[1]->getRelayUrl());
        $this->assertSame(Nip10Marker::Root, $events[1]->getMarker());
        $this->assertNull($events[1]->getAuthor());
    }

    public function testExtractReferencesExtractsPubkeyTags(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'alice'],
            ['p', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $pubkeys = $references->getPubkeys()->toArray();
        $this->assertCount(2, $pubkeys);
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $pubkeys[0]->getPubkey()->toHex());
        $this->assertEquals('wss://relay.com', (string) $pubkeys[0]->getRelayUrl());
        $this->assertEquals('alice', $pubkeys[0]->getPetname());
        $this->assertEquals('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $pubkeys[1]->getPubkey()->toHex());
        $this->assertNull($pubkeys[1]->getRelayUrl());
        $this->assertNull($pubkeys[1]->getPetname());
    }

    public function testExtractReferencesExtractsQuoteTags(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['q', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'wss://relay.com', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['q', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $quotes = $references->getQuotes()->toArray();
        $this->assertCount(2, $quotes);
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

        $tags = TagCollectionMother::fromRaw([
            ['a', "30023:{$pubkey1}:my-article", 'wss://relay.com'],
            ['a', "30001:{$pubkey2}:bookmark-list"],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $addressable = $references->getAddressable()->toArray();
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
        $tags = TagCollectionMother::fromRaw([
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

        $tags = TagCollectionMother::fromRaw([
            ['a', 'invalid_format'],
            ['a', 'only_one_part'],
            ['a', '1:invalidpubkey:identifier'],
            ['a', '30023:badpubkey:identifier'],
            ['a', "10002:{$validPubkey}:x"],
            ['a', "30023:{$validPubkey}:my-article"],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $addressable = $references->getAddressable()->toArray();
        $this->assertCount(1, $addressable);
        $this->assertEquals(30023, $addressable[0]->getKind()->toInt());
    }

    public function testExtractReferencesReturnsEmptyForUnknownTags(): void
    {
        $tags = TagCollectionMother::fromRaw([
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
        $tags = TagCollectionMother::fromRaw([
            ['r', 'wss://relay.com', 'read'],
            ['r', 'wss://other.com'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $relays = $references->getRelays()->toArray();
        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay.com', (string) $relays[0]->getRelayUrl());
        $this->assertSame(RelayMarker::Read, $relays[0]->getMarker());
        $this->assertEquals('wss://other.com', (string) $relays[1]->getRelayUrl());
        $this->assertSame(RelayMarker::Both, $relays[1]->getMarker());
    }

    public function testExtractReferencesExtractsChallengeTags(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['challenge', 'abc123'],
        ]);

        $references = TagReferenceExtractor::extract($tags);

        $this->assertSame(['abc123'], $references->getChallenges()->toStrings());
    }

    public function testAnalyseReplyChainForRootPost(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['p', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'],
            ['subject', 'Hello World'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertFalse($replyChain->isReply());
        $this->assertFalse($replyChain->isReply());
        $this->assertNull(self::rootEvent($replyChain));
        $this->assertNull(self::parentEvent($replyChain));
        $this->assertCount(1, $replyChain->getConversationParticipants());
        $this->assertEmpty($replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainWithMarkers(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'wss://relay.com', 'root'],
            ['e', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', 'wss://relay.com', 'reply'],
            ['p', 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertTrue($replyChain->isReply());
        $this->assertEquals('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', self::rootEvent($replyChain)?->getEventId()->toHex());
        $this->assertSame(Nip10Marker::Root, self::rootEvent($replyChain)?->getMarker());
        $this->assertEquals('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', self::parentEvent($replyChain)?->getEventId()->toHex());
        $this->assertSame(Nip10Marker::Reply, self::parentEvent($replyChain)?->getMarker());
        $this->assertCount(1, $replyChain->getConversationParticipants());
        $this->assertEmpty($replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainSingleEventReply(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'wss://relay.com'],
            ['p', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertTrue($replyChain->isReply());
        $this->assertNull(self::rootEvent($replyChain));
        $this->assertEquals('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', self::parentEvent($replyChain)?->getEventId()->toHex());
    }

    public function testAnalyseReplyChainMultipleEventsWithoutMarkers(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay1.com'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay2.com'],
            ['e', '3333333333333333333333333333333333333333333333333333333333333333', 'wss://relay3.com'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['p', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', self::rootEvent($replyChain)?->getEventId()->toHex());
        $this->assertEquals('3333333333333333333333333333333333333333333333333333333333333333', self::parentEvent($replyChain)?->getEventId()->toHex());
        $this->assertCount(1, $replyChain->getMentionedEvents());
        $this->assertEquals('2222222222222222222222222222222222222222222222222222222222222222', $replyChain->getMentionedEvents()->toArray()[0]->getEventId()->toHex());
        $this->assertCount(2, $replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainWithMentions(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', '', 'root'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', '', 'mention'],
            ['e', '3333333333333333333333333333333333333333333333333333333333333333'],
            ['e', '4444444444444444444444444444444444444444444444444444444444444444', '', 'reply'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', self::rootEvent($replyChain)?->getEventId()->toHex());
        $this->assertEquals('4444444444444444444444444444444444444444444444444444444444444444', self::parentEvent($replyChain)?->getEventId()->toHex());
        $this->assertCount(2, $replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainWithAuthor(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'reply', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            ['p', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $parentEvent = self::parentEvent($replyChain);
        $this->assertNotNull($parentEvent);
        $this->assertEquals('wss://relay.com', (string) $parentEvent->getRelayUrl());
        $this->assertSame(Nip10Marker::Reply, $parentEvent->getMarker());
        $this->assertNotNull($parentEvent->getAuthor());
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $parentEvent->getAuthor()->toHex());
    }

    public function testAnEventTagThatDoesNotParseIsNotAReply(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', 'invalid_hex', 'wss://relay.com'],
            ['p', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertFalse($replyChain->isReply());
        $this->assertNull(self::rootEvent($replyChain));
        $this->assertNull(self::parentEvent($replyChain));
    }

    public function testAnalyseReplyChainHandlesInvalidPubkeys(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'],
            ['p', 'invalid_pubkey_format'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertEmpty($replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainHandlesInvalidRelayUrls(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'invalid-url'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($replyChain->isReply());
        $this->assertNull(self::parentEvent($replyChain)?->getRelayUrl());
    }

    public function testAnalyseReplyChainNullKindUsesNip10Logic(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', 'wss://relay.com', 'root'],
            ['e', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', 'wss://relay.com', 'reply'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, null);

        $this->assertTrue($replyChain->isReply());
        $this->assertSame(Nip10Marker::Root, self::rootEvent($replyChain)?->getMarker());
        $this->assertSame(Nip10Marker::Reply, self::parentEvent($replyChain)?->getMarker());
    }

    public function testAnalyseReplyChainCommentWithRootAndParent(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $parentId = '2222222222222222222222222222222222222222222222222222222222222222';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $parentAuthor = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $participant = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

        $tags = TagCollectionMother::fromRaw([
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['e', $parentId, 'wss://relay.com', $parentAuthor],
            ['p', $participant],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($replyChain->isReply());
        $this->assertTrue($replyChain->isReply());
        $root = self::rootEvent($replyChain);
        $parent = self::parentEvent($replyChain);
        $this->assertNotNull($root);
        $this->assertNotNull($parent);
        $this->assertSame($rootId, $root->getEventId()->toHex());
        $this->assertNull($root->getMarker());
        $this->assertSame($rootAuthor, $root->getAuthor()?->toHex());
        $this->assertSame($parentId, $parent->getEventId()->toHex());
        $this->assertNull($parent->getMarker());
        $this->assertSame($parentAuthor, $parent->getAuthor()?->toHex());

        $this->assertCount(1, $replyChain->getConversationParticipants());
        $this->assertEmpty($replyChain->getMentionedEvents());
    }

    public function testAnalyseReplyChainCommentWithOnlyRootTag(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $tags = TagCollectionMother::fromRaw([
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($replyChain->isReply());
        $this->assertTrue($replyChain->isReply());
        $this->assertNull(self::parentEvent($replyChain));
    }

    public function testAnalyseReplyChainCommentWithOnlyParentTag(): void
    {
        $parentId = '2222222222222222222222222222222222222222222222222222222222222222';
        $parentAuthor = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        $tags = TagCollectionMother::fromRaw([
            ['e', $parentId, 'wss://relay.com', $parentAuthor],
            ['K', 'web'],
            ['k', '1111'],
            ['I', 'https://example.com'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($replyChain->isReply());
        $this->assertNull(self::rootEvent($replyChain));
        $this->assertSame($parentId, self::parentEvent($replyChain)?->getEventId()->toHex());
    }

    public function testAnalyseReplyChainCommentPosition3IsPubkeyNotMarker(): void
    {
        $eventId = '1111111111111111111111111111111111111111111111111111111111111111';
        $authorPubkey = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $tags = TagCollectionMother::fromRaw([
            ['e', $eventId, 'wss://relay.com', $authorPubkey],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $parentEvent = self::parentEvent($replyChain);
        $this->assertNotNull($parentEvent);
        $this->assertNull($parentEvent->getMarker());
        $this->assertNotNull($parentEvent->getAuthor());
        $this->assertSame($authorPubkey, $parentEvent->getAuthor()->toHex());
    }

    public function testAnalyseReplyChainCommentGracefullySkipsInvalidIds(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['E', 'invalid_hex', 'wss://relay.com', 'also_invalid'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay.com', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            ['p', 'invalid_pubkey'],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull(self::rootEvent($replyChain));
        $this->assertSame('2222222222222222222222222222222222222222222222222222222222222222', self::parentEvent($replyChain)?->getEventId()->toHex());
        $this->assertEmpty($replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainKind1StillUsesNip10Logic(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'root'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay.com', 'reply'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertTrue($replyChain->isReply());
        $this->assertSame(Nip10Marker::Root, self::rootEvent($replyChain)?->getMarker());
        $this->assertSame(Nip10Marker::Reply, self::parentEvent($replyChain)?->getMarker());
    }

    public function testAnalyseReplyChainCommentWithOnlyRootTagIsReply(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $tags = TagCollectionMother::fromRaw([
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['p', $rootAuthor],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($replyChain->isReply());
        $this->assertTrue($replyChain->isReply());
        $this->assertSame($rootId, self::rootEvent($replyChain)?->getEventId()->toHex());
        $this->assertNull(self::parentEvent($replyChain));
        $this->assertCount(1, $replyChain->getConversationParticipants());
    }

    public function testAnalyseReplyChainCommentWithNoEventTagsResolvesItsExternalRoot(): void
    {
        $tags = TagCollectionMother::fromRaw([
            ['I', 'https://example.com'],
            ['K', 'web'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($replyChain->isReply());
        $this->assertSame('https://example.com', self::rootExternal($replyChain)?->getValue());
        $this->assertNull(self::rootEvent($replyChain));
        $this->assertNull(self::parentEvent($replyChain));
    }

    public function testGetHashtagsReadsBackTheLowercasedTagValues(): void
    {
        $tags = new TagCollection([
            Tag::hashtag(Hashtag::fromString('NoStR')),
            Tag::hashtag(Hashtag::fromString('bitcoin')),
        ]);

        $this->assertSame(['nostr', 'bitcoin'], $tags->getHashtags()->toStrings());
    }

    public function testGetHashtagsIsEmptyWhenThereAreNoHashtagTags(): void
    {
        $this->assertCount(0, new TagCollection()->getHashtags());
    }

    public function testGetHashtagsAnswersTheSameAsExtractingThemFromContent(): void
    {
        $tags = new TagCollection([
            Tag::hashtag(Hashtag::fromString('Nostr')),
            Tag::hashtag(Hashtag::fromString('nostr')),
            Tag::hashtag(Hashtag::fromString('NOSTR')),
        ]);

        $this->assertSame(
            EventContent::fromString('#Nostr #nostr #NOSTR')->extractHashtags()->toStrings(),
            $tags->getHashtags()->toStrings(),
        );
    }

    public function testGetSoleValueByTypeReadsTheOneValueRepeatedTagsCarry(): void
    {
        $collection = new TagCollection([Tag::fromArray(['relay', 'wss://a']), Tag::fromArray(['relay', 'wss://a'])]);

        $this->assertSame('wss://a', $collection->getSoleValueByType(TagType::fromString(TagType::RELAY))->getValue());
    }

    public function testGetSoleValueByTypeStatesOneValueWhenTheTagsAgree(): void
    {
        $collection = new TagCollection([Tag::fromArray(['relay', 'wss://a'])]);

        $this->assertSame(SoleTagValueState::One, $collection->getSoleValueByType(TagType::fromString(TagType::RELAY))->getState());
    }

    public function testGetSoleValueByTypeStatesDisagreementWhenTwoValuesDiffer(): void
    {
        $collection = new TagCollection([Tag::fromArray(['relay', 'wss://a']), Tag::fromArray(['relay', 'wss://b'])]);

        $this->assertSame(SoleTagValueState::Disagreeing, $collection->getSoleValueByType(TagType::fromString(TagType::RELAY))->getState());
    }

    public function testGetSoleValueByTypeHasNoValueWhenTwoValuesDiffer(): void
    {
        $collection = new TagCollection([Tag::fromArray(['relay', 'wss://a']), Tag::fromArray(['relay', 'wss://b'])]);

        $this->assertNull($collection->getSoleValueByType(TagType::fromString(TagType::RELAY))->getValue());
    }

    public function testGetSoleValueByTypeStatesAbsenceWithoutTheTag(): void
    {
        $this->assertSame(SoleTagValueState::Absent, new TagCollection()->getSoleValueByType(TagType::fromString(TagType::RELAY))->getState());
    }

    public function testGetSoleValueByTypeHasNoValueWithoutTheTag(): void
    {
        $this->assertNull(new TagCollection()->getSoleValueByType(TagType::fromString(TagType::RELAY))->getValue());
    }

    public function testGetSoleValueByTypeStatesAbsenceForATagWithoutAValue(): void
    {
        $collection = new TagCollection([Tag::fromArray(['relay'])]);

        $this->assertSame(SoleTagValueState::Absent, $collection->getSoleValueByType(TagType::fromString(TagType::RELAY))->getState());
    }

    public function testGetSoleValueByTypeReadsTheEmptyStringAsAValue(): void
    {
        $collection = new TagCollection([Tag::fromArray(['relay', ''])]);

        $this->assertSame('', $collection->getSoleValueByType(TagType::fromString(TagType::RELAY))->getValue());
    }

    public function testGetIdentifierIsTheEmptyStringWithoutADTag(): void
    {
        $this->assertSame('', new TagCollection()->getIdentifier());
    }

    public function testGetIdentifierReadsARepeatedValueAsOneClaim(): void
    {
        $collection = new TagCollection([Tag::identifier('post'), Tag::identifier('post')]);

        $this->assertSame('post', $collection->getIdentifier());
    }

    public function testGetIdentifierIsNullWhenDTagsDisagree(): void
    {
        $collection = new TagCollection([Tag::identifier('first'), Tag::identifier('second')]);

        $this->assertNull($collection->getIdentifier());
    }

    public function testGetIdentifierReadsAnEmptyDTagAsTheEmptyIdentifier(): void
    {
        $this->assertSame('', new TagCollection([Tag::identifier('')])->getIdentifier());
    }

    public function testGetSolePubkeyByTypeReadsTheOnePubkey(): void
    {
        $pubkey = str_repeat('ab', 32);
        $collection = new TagCollection([Tag::fromArray(['p', $pubkey]), Tag::fromArray(['p', $pubkey])]);

        $this->assertSame($pubkey, $collection->getSolePubkeyByType(TagType::pubkey())?->toHex());
    }

    public function testGetSolePubkeyByTypeIsNullWhenPubkeysDisagree(): void
    {
        $collection = new TagCollection([Tag::fromArray(['p', str_repeat('ab', 32)]), Tag::fromArray(['p', str_repeat('cd', 32)])]);

        $this->assertNull($collection->getSolePubkeyByType(TagType::pubkey()));
    }

    public function testGetSolePubkeyByTypeIsNullWhenTheValueIsNotAPubkey(): void
    {
        $this->assertNull(new TagCollection([Tag::fromArray(['p', 'not-a-key'])])->getSolePubkeyByType(TagType::pubkey()));
    }

    public function testGetPublishedAtReadsTheSolePublishedAtTag(): void
    {
        $collection = TagCollectionMother::fromRaw([['published_at', '1700000000'], ['published_at', '1700000000']]);

        $this->assertSame(1700000000, $collection->getPublishedAt()?->toInt());
    }

    /**
     * @param list<list<string>> $tags
     */
    #[DataProvider('tagsNamingNoPublicationInstant')]
    public function testGetPublishedAtIsNullWithoutOneReadableInstant(array $tags): void
    {
        $this->assertNull(TagCollectionMother::fromRaw($tags)->getPublishedAt());
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function tagsNamingNoPublicationInstant(): iterable
    {
        yield 'no tag' => [[]];
        yield 'tags that disagree' => [[['published_at', '1700000000'], ['published_at', '1700000001']]];
        yield 'a negative value' => [[['published_at', '-1']]];
        yield 'a value that is not a decimal' => [[['published_at', 'yesterday']]];
    }

    private static function rootEvent(ReplyChain $chain): ?EventReference
    {
        $pointer = $chain->getRoot();

        return $pointer instanceof EventReference ? $pointer : null;
    }

    private static function parentEvent(ReplyChain $chain): ?EventReference
    {
        $pointer = $chain->getParent();

        return $pointer instanceof EventReference ? $pointer : null;
    }

    private static function rootExternal(ReplyChain $chain): ?ExternalContentId
    {
        $pointer = $chain->getRoot();

        return $pointer instanceof ExternalContentId ? $pointer : null;
    }
}
