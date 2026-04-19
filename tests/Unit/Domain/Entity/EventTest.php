<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventTest extends TestCase
{
    use WithCryptoServices;

    private KeyPair $keyPair;
    private Event $event;

    protected function setUp(): void
    {
        $this->keyPair = KeyPair::generate($this->signatureService());

        $this->event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr!')
        );
    }

    public function testCanCreateEvent(): void
    {
        $this->assertInstanceOf(Event::class, $this->event);
        $this->assertTrue($this->event->getPubkey()->equals($this->keyPair->getPublicKey()));
        $this->assertTrue($this->event->getKind()->equals(EventKind::textNote()));
        $this->assertSame('Hello Nostr!', (string) $this->event->getContent());
        $this->assertFalse($this->event->isSigned());
    }

    public function testCanSignEvent(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());

        $this->assertTrue($signedEvent->isSigned());
        $this->assertInstanceOf(Signature::class, $signedEvent->getSignature());
        $this->assertInstanceOf(EventId::class, $signedEvent->getId());
        $this->assertNotSame($this->event, $signedEvent);
    }

    public function testThrowsExceptionWhenSigningWithWrongPrivateKey(): void
    {
        $wrongKeyPair = KeyPair::generate($this->signatureService());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Private key does not match event public key');

        $this->event->sign($wrongKeyPair->getPrivateKey(), $this->signatureService());
    }

    public function testCanVerifyValidSignature(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());

        $this->assertTrue($signedEvent->verify($this->signatureService()));
    }

    public function testUnsignedEventFailsVerification(): void
    {
        $this->assertFalse($this->event->verify($this->signatureService()));
    }

    public function testCanCalculateEventId(): void
    {
        $calculatedId1 = $this->event->calculateId();
        $calculatedId2 = $this->event->calculateId();

        $this->assertTrue($calculatedId1->equals($calculatedId2));
        $this->assertSame(64, strlen($calculatedId1->toHex()));
    }

    public function testGetIdReturnsCalculatedIdForUnsignedEvent(): void
    {
        $calculatedId = $this->event->calculateId();
        $getId = $this->event->getId();

        $this->assertTrue($calculatedId->equals($getId));
    }

    public function testGetIdReturnsStoredIdForSignedEvent(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());
        $storedId = $signedEvent->getId();
        $calculatedId = $signedEvent->calculateId();

        $this->assertTrue($storedId->equals($calculatedId));
    }

    public function testCanConvertToArray(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());
        $array = $signedEvent->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('pubkey', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('kind', $array);
        $this->assertArrayHasKey('tags', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('sig', $array);

        $this->assertSame($signedEvent->getId()->toHex(), $array['id']);
        $this->assertSame($signedEvent->getPubkey()->toHex(), $array['pubkey']);
        $this->assertSame($signedEvent->getCreatedAt()->toInt(), $array['created_at']);
        $this->assertSame($signedEvent->getKind()->toInt(), $array['kind']);
        $this->assertSame($signedEvent->getTags()->toArray(), $array['tags']);
        $this->assertSame((string) $signedEvent->getContent(), $array['content']);
        $signature = $signedEvent->getSignature();
        $this->assertNotNull($signature);
        $this->assertSame($signature->toHex(), $array['sig']);
    }

    public function testUnsignedEventToArrayHasEmptySignature(): void
    {
        $array = $this->event->toArray();

        $this->assertSame('', $array['sig']);
    }

    public function testCanCreateFromArray(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());
        $array = $signedEvent->toArray();

        $recreatedEvent = Event::fromArray($array);

        $this->assertTrue($recreatedEvent->getId()->equals($signedEvent->getId()));
        $this->assertTrue($recreatedEvent->getPubkey()->equals($signedEvent->getPubkey()));
        $this->assertTrue($recreatedEvent->getCreatedAt()->equals($signedEvent->getCreatedAt()));
        $this->assertTrue($recreatedEvent->getKind()->equals($signedEvent->getKind()));
        $this->assertTrue($recreatedEvent->getTags()->equals($signedEvent->getTags()));
        $this->assertTrue($recreatedEvent->getContent()->equals($signedEvent->getContent()));
        $recreatedSignature = $recreatedEvent->getSignature();
        $signedSignature = $signedEvent->getSignature();
        $this->assertNotNull($recreatedSignature);
        $this->assertNotNull($signedSignature);
        $this->assertTrue($recreatedSignature->equals($signedSignature));
    }

    public function testFromArrayThrowsExceptionForMissingRequiredFields(): void
    {
        $incompleteArray = [
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => time(),
            // Missing 'kind', 'tags', 'content'
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: kind');

        Event::fromArray($incompleteArray);
    }

    public function testFromArrayCanCreateUnsignedEvent(): void
    {
        $array = [
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => time(),
            'kind' => 1,
            'tags' => [],
            'content' => 'Hello',
        ];

        $event = Event::fromArray($array);

        $this->assertFalse($event->isSigned());
        $this->assertNull($event->getSignature());
    }

    public function testFromArrayCanCreateSignedEvent(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());
        $array = $signedEvent->toArray();

        $recreatedEvent = Event::fromArray($array);

        $this->assertTrue($recreatedEvent->isSigned());
        $this->assertNotNull($recreatedEvent->getSignature());
    }

    public function testEventIdCalculationIsConsistent(): void
    {
        $event1 = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $event2 = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test')
        );

        $this->assertTrue($event1->calculateId()->equals($event2->calculateId()));
    }

    public function testDifferentContentProducesDifferentIds(): void
    {
        $event1 = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test1')
        );

        $event2 = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1234567890),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test2')
        );

        $this->assertFalse($event1->calculateId()->equals($event2->calculateId()));
    }

    public function testRoundTripSerialisation(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());
        $array = $signedEvent->toArray();
        $recreatedEvent = Event::fromArray($array);
        $recreatedArray = $recreatedEvent->toArray();

        $this->assertSame($array, $recreatedArray);
    }

    public function testIsReplyReturnsFalseForEventWithNoEventTags(): void
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('This is not a reply')
        );

        $this->assertFalse($event->isReply());
    }

    public function testIsReplyReturnsTrueForEventWithEventTagsNoMarker(): void
    {
        // Deprecated positional scheme - no marker means it's a reply
        $tags = TagCollection::fromArray([['e', '1234567890abcdef']]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This is a reply')
        );

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsTrueForEventWithRootMarker(): void
    {
        $tags = TagCollection::fromArray([['e', '1234567890abcdef', '', 'root']]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This is a reply to root')
        );

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsTrueForEventWithReplyMarker(): void
    {
        $tags = TagCollection::fromArray([['e', '1234567890abcdef', '', 'reply']]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This is a reply')
        );

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsFalseForEventWithOnlyMentionMarker(): void
    {
        // Per NIP-10: "mention" marker means inline reference, NOT a reply
        $tags = TagCollection::fromArray([['e', '1234567890abcdef', '', 'mention']]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This mentions an event but is not a reply')
        );

        $this->assertFalse($event->isReply());
    }

    public function testIsReplyReturnsFalseForEventWithMultipleMentionMarkers(): void
    {
        // Multiple mention markers still not a reply
        $tags = TagCollection::fromArray([
            ['e', '1234567890abcdef', '', 'mention'],
            ['e', 'fedcba0987654321', '', 'mention'],
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This mentions events but is not a reply')
        );

        $this->assertFalse($event->isReply());
    }

    public function testIsReplyReturnsTrueForMixedMentionAndRootMarkers(): void
    {
        // If there's at least one root/reply marker, it's a reply
        $tags = TagCollection::fromArray([
            ['e', '1234567890abcdef', '', 'root'],
            ['e', 'fedcba0987654321', '', 'mention'],
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This is a reply that also mentions')
        );

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsTrueForEventWithEmptyMarker(): void
    {
        // Empty string marker = deprecated scheme = reply
        $tags = TagCollection::fromArray([['e', '1234567890abcdef', 'wss://relay.example.com', '']]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            $tags,
            EventContent::fromString('This is a reply')
        );

        $this->assertTrue($event->isReply());
    }

    public function testAnalyseReplyChainKind1111UsesNip22Logic(): void
    {
        $rootId = '1111111111111111111111111111111111111111111111111111111111111111';
        $parentId = '2222222222222222222222222222222222222222222222222222222222222222';
        $rootAuthor = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $parentAuthor = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        $event = $this->createEventWithKindAndContent(1111, 'A comment', [
            ['E', $rootId, 'wss://relay.com', $rootAuthor],
            ['e', $parentId, 'wss://relay.com', $parentAuthor],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($event->getTags(), $event->getKind());

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertNull($replyChain->getRootEvent()->getMarker());
        $this->assertNotNull($replyChain->getRootEvent()->getAuthor());
        $this->assertSame($rootAuthor, $replyChain->getRootEvent()->getAuthor()->toHex());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertNull($replyChain->getParentEvent()->getMarker());
        $this->assertNotNull($replyChain->getParentEvent()->getAuthor());
        $this->assertSame($parentAuthor, $replyChain->getParentEvent()->getAuthor()->toHex());
    }

    public function testAnalyseReplyChainKind1UsesNip10Logic(): void
    {
        $event = $this->createEventWithKindAndContent(1, 'A reply', [
            ['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'root'],
            ['e', '2222222222222222222222222222222222222222222222222222222222222222', 'wss://relay.com', 'reply'],
        ]);

        $replyChain = ReplyChainAnalyser::analyse($event->getTags(), $event->getKind());

        $this->assertTrue($replyChain->isReply());
        $this->assertNotNull($replyChain->getRootEvent());
        $this->assertSame('root', $replyChain->getRootEvent()->getMarker());
        $this->assertNotNull($replyChain->getParentEvent());
        $this->assertSame('reply', $replyChain->getParentEvent()->getMarker());
    }

    public function testIsRepostReturnsTrueForRepostKind(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::REPOST, '', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'],
        ]);

        $this->assertTrue($event->isRepost());
    }

    public function testIsRepostReturnsTrueForGenericRepostKind(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::GENERIC_REPOST, '', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'],
        ]);

        $this->assertTrue($event->isRepost());
    }

    public function testIsRepostReturnsFalseForTextNoteKind(): void
    {
        $this->assertFalse($this->event->isRepost());
    }

    public function testIsReplyReturnsFalseForRepostWithEventTags(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::REPOST, '', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'],
        ]);

        $this->assertFalse($event->isReply());
    }

    public function testIsReplyReturnsFalseForGenericRepostWithEventTags(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::GENERIC_REPOST, '', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'],
        ]);

        $this->assertFalse($event->isReply());
    }

    public function testIsReplyReturnsTrueForCommentKindWithEventTags(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::COMMENT, 'A comment', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', 'wss://relay.com', str_repeat('a', 64)],
        ]);

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsTrueForCommentKindWithOnlyRootEventTag(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::COMMENT, 'A direct comment on root', [
            ['E', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', 'wss://relay.com', str_repeat('a', 64)],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsTrueForCommentKindWithNoEventTags(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::COMMENT, 'A comment on external content', [
            ['I', 'https://example.com'],
            ['K', 'web'],
            ['k', '1111'],
        ]);

        $this->assertTrue($event->isReply());
    }

    public function testIsReplyReturnsTrueForCommentKindWithRootAndParentTags(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::COMMENT, 'A nested comment', [
            ['E', str_repeat('1', 64), 'wss://relay.com', str_repeat('a', 64)],
            ['e', str_repeat('2', 64), 'wss://relay.com', str_repeat('b', 64)],
            ['K', '1'],
            ['k', '1111'],
        ]);

        $this->assertTrue($event->isReply());
    }

    public function testGetPublishedAtReturnsTimestampWhenTagExists(): void
    {
        $event = $this->createEventWithKindAndContent(1, 'Test', [
            ['published_at', '1700000000'],
        ]);

        $publishedAt = $event->getPublishedAt();
        $this->assertNotNull($publishedAt);
        $this->assertSame(1700000000, $publishedAt->toInt());
    }

    public function testGetPublishedAtReturnsNullWhenNoTag(): void
    {
        $this->assertNull($this->event->getPublishedAt());
    }

    public function testWithTagsReturnsNewEventWithReplacedTags(): void
    {
        $newTags = new TagCollection([Tag::hashtag('nostr')]);
        $newEvent = $this->event->withTags($newTags);

        $this->assertTrue($this->event->getTags()->isEmpty());
        $this->assertFalse($newEvent->getTags()->isEmpty());
        $this->assertTrue($newEvent->getTags()->equals($newTags));
    }

    public function testWithTagsPreservesOtherFields(): void
    {
        $newTags = new TagCollection([Tag::hashtag('nostr')]);
        $newEvent = $this->event->withTags($newTags);

        $this->assertTrue($newEvent->getPubkey()->equals($this->event->getPubkey()));
        $this->assertTrue($newEvent->getKind()->equals($this->event->getKind()));
        $this->assertTrue($newEvent->getContent()->equals($this->event->getContent()));
        $this->assertTrue($newEvent->getCreatedAt()->equals($this->event->getCreatedAt()));
    }

    public function testToStringReturnsEventIdForSignedEvent(): void
    {
        $signedEvent = $this->event->sign($this->keyPair->getPrivateKey(), $this->signatureService());

        $this->assertSame($signedEvent->getId()->toHex(), (string) $signedEvent);
    }

    public function testToStringReturnsEmptyStringForUnsignedEvent(): void
    {
        $this->assertSame('', (string) $this->event);
    }

    public function testFromArrayHandlesNonStringContent(): void
    {
        $array = [
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1234567890,
            'kind' => 1,
            'tags' => [],
            'content' => ['key' => 'value'],
        ];

        $event = Event::fromArray($array);

        $this->assertSame('{"key":"value"}', (string) $event->getContent());
    }

    public function testFromArrayHandlesEmptySignature(): void
    {
        $array = [
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1234567890,
            'kind' => 1,
            'tags' => [],
            'content' => 'test',
            'sig' => '',
        ];

        $event = Event::fromArray($array);

        $this->assertFalse($event->isSigned());
        $this->assertNull($event->getSignature());
    }

    public function testIsDeletionReturnsTrueForKind5(): void
    {
        $event = $this->createEventWithKindAndContent(EventKind::EVENT_DELETION, '', [
            ['e', str_repeat('a', 64)],
        ]);

        $this->assertTrue($event->isDeletion());
    }

    public function testIsDeletionReturnsFalseForTextNote(): void
    {
        $this->assertFalse($this->event->isDeletion());
    }

    public function testIsExpiredReturnsFalseWithNoExpirationTag(): void
    {
        $this->assertFalse($this->event->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpired(): void
    {
        $event = $this->createEventWithKindAndContent(1, 'test', [
            ['expiration', (string) (time() - 3600)],
        ]);

        $this->assertTrue($event->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenNotYetExpired(): void
    {
        $event = $this->createEventWithKindAndContent(1, 'test', [
            ['expiration', (string) (time() + 3600)],
        ]);

        $this->assertFalse($event->isExpired());
    }

    public function testIsProtectedReturnsTrueWithProtectedTag(): void
    {
        $event = $this->createEventWithKindAndContent(1, 'test', [
            ['-'],
        ]);

        $this->assertTrue($event->isProtected());
    }

    public function testIsProtectedReturnsFalseWithoutProtectedTag(): void
    {
        $this->assertFalse($this->event->isProtected());
    }

    public function testCalculateIdAndVerifyHandleParagraphSeparatorInContent(): void
    {
        $event = Event::fromArray([
            'id' => 'ebb6b3d01d4f5ade21554c70ccc18d663a9765573ba42eac6ff4c504a0b81111',
            'pubkey' => '910a1d5c845b9eb04787fa339651e05883eca8045d804d5a40e9d7e2737ff460',
            'created_at' => 1773410433,
            'kind' => 0,
            'tags' => [
                ['proxy', 'https://infosec.exchange/users/spamhaus', 'activitypub'],
                ['client', 'Mostr', '31990:6be38f8c63df7dbf84db7ec4a6e6fbbd8d19dca3b980efad18585c46f04b26f9:mostr', 'wss://relay.ditto.pub'],
            ],
            'content' => '{"name":"The Spamhaus Project","about":"Spamhaus strengthens trust and safety for the Internet. Advocating for change through sharing reliable intelligence and expertise. As the authority on IP and domain reputation data, we are trusted across the industry because of our strong ethics, impartiality, and quality of actionable data. This data not only protects but also provides signal and insight across networks and email worldwide. '."\u{2029}".'With over two decades of experience, our researchers and threat hunters focus on exposing malicious activity to make the internet a better place for everyone. A wide range of industries, including leading global technology companies, use Spamhaus\' data; currently protecting over 4.5 billion mailboxes worldwide.","picture":"https://media.infosec.exchange/infosec.exchange/accounts/avatars/109/320/853/817/139/353/original/9bf10cbd9f875bcd.jpeg","banner":"https://media.infosec.exchange/infosec.exchange/accounts/headers/109/320/853/817/139/353/original/04fec027cdcf80eb.jpg","nip05":"spamhaus@infosec-exchange.mostr.pub","fields":[["Website","https://www.spamhaus.org"],["Threat Intel Community","https://submit.spamhaus.org"],["LinkedIn","https://www.linkedin.com/company/the-spamhaus-project"],["Twitter","https://twitter.com/spamhaus"]]}',
            'sig' => '7614f8586aacb36e5a501d2f11b0501faa070ab0d90434f7e81bd7dbde4cabb935e80a0064f9db2ba8db2f673ec510ade473a855d1407572ba53873fc13f3290',
        ]);

        $this->assertSame(
            'ebb6b3d01d4f5ade21554c70ccc18d663a9765573ba42eac6ff4c504a0b81111',
            $event->calculateId()->toHex(),
            'calculateId() must emit U+2029 verbatim per NIP-01'
        );
        $this->assertTrue($event->verify($this->signatureService()), 'verify() must succeed for an event whose content contains U+2029');
    }

    private function createEventWithKindAndContent(int $kind, string $content, array $tagArrays): Event
    {
        $tags = [];
        foreach ($tagArrays as $tagArray) {
            $tags[] = Tag::fromArray($tagArray);
        }

        return new Event(
            PublicKey::fromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt($kind),
            new TagCollection($tags),
            EventContent::fromString($content)
        );
    }
}
