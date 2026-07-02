<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RumourTest extends TestCase
{
    private KeyPair $keyPair;
    private Rumour $rumour;

    protected function setUp(): void
    {
        $this->keyPair = KeyMother::alice();

        $this->rumour = new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('Hello Nostr!')
        );
    }

    public function testCanCreateRumour(): void
    {
        $this->assertTrue($this->rumour->getPubkey()->equals($this->keyPair->getPublicKey()));
        $this->assertTrue($this->rumour->getKind()->is(EventKind::TEXT_NOTE));
        $this->assertSame('Hello Nostr!', (string) $this->rumour->getContent());
    }

    public function testSignMintsASignedEvent(): void
    {
        $event = $this->rumour->sign($this->keyPair, FakeSignatureService::accepting());

        $this->assertTrue($event->getId()->equals($this->rumour->getId()));
        $this->assertTrue($event->verify(FakeSignatureService::accepting()));
    }

    public function testSignThrowsWhenKeyPairDoesNotMatchPubkey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key pair does not match rumour public key');

        $this->rumour->sign(KeyMother::bob(), FakeSignatureService::accepting());
    }

    public function testGetIdIsStable(): void
    {
        $this->assertTrue($this->rumour->getId()->equals($this->rumour->getId()));
        $this->assertSame(64, strlen($this->rumour->getId()->toHex()));
    }

    public function testIdCalculationIsConsistent(): void
    {
        $first = new Rumour($this->keyPair->getPublicKey(), Timestamp::fromInt(1234567890), EventKind::fromInt(EventKind::TEXT_NOTE), new TagCollection(), EventContent::fromString('test'));
        $second = new Rumour($this->keyPair->getPublicKey(), Timestamp::fromInt(1234567890), EventKind::fromInt(EventKind::TEXT_NOTE), new TagCollection(), EventContent::fromString('test'));

        $this->assertTrue($first->getId()->equals($second->getId()));
    }

    public function testDifferentContentProducesDifferentIds(): void
    {
        $first = new Rumour($this->keyPair->getPublicKey(), Timestamp::fromInt(1234567890), EventKind::fromInt(EventKind::TEXT_NOTE), new TagCollection(), EventContent::fromString('test1'));
        $second = new Rumour($this->keyPair->getPublicKey(), Timestamp::fromInt(1234567890), EventKind::fromInt(EventKind::TEXT_NOTE), new TagCollection(), EventContent::fromString('test2'));

        $this->assertFalse($first->getId()->equals($second->getId()));
    }

    public function testToArrayHasComputedIdAndOmitsSignature(): void
    {
        $array = $this->rumour->toArray();

        $this->assertSame($this->rumour->getId()->toHex(), $array['id']);
        $this->assertSame($this->rumour->getPubkey()->toHex(), $array['pubkey']);
        $this->assertSame($this->rumour->getCreatedAt()->toInt(), $array['created_at']);
        $this->assertSame($this->rumour->getKind()->toInt(), $array['kind']);
        $this->assertSame($this->rumour->getTags()->toJsonArray(), $array['tags']);
        $this->assertSame((string) $this->rumour->getContent(), $array['content']);
        $this->assertArrayNotHasKey('sig', $array);
    }

    public function testToJsonRoundTrips(): void
    {
        $decoded = json_decode($this->rumour->toJson(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($this->rumour->toArray(), $decoded);
    }

    public function testFromArrayParsesTheUnsignedCore(): void
    {
        $rumour = Rumour::fromArray([
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => 'Hello',
        ]);

        $this->assertNotNull($rumour);
        $this->assertSame('Hello', (string) $rumour->getContent());
    }

    public function testFromArrayIgnoresSignatureFields(): void
    {
        $rumour = Rumour::fromArray([
            'id' => str_repeat('f', 64),
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => 'Hello',
            'sig' => str_repeat('a', 128),
        ]);

        $this->assertNotNull($rumour);
        $this->assertTrue($rumour->getId()->equals($this->recreateId('Hello')));
    }

    public function testFromArrayReturnsNullForMissingRequiredFields(): void
    {
        $this->assertNull(Rumour::fromArray([
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1700000000,
        ]));
    }

    public function testFromArrayReturnsNullForInvalidUtf8Content(): void
    {
        $this->assertNull(Rumour::fromArray([
            'pubkey' => str_repeat('a', 64),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => "bad\xff\xfeutf8",
        ]));
    }

    public function testFromArrayHandlesNonStringContent(): void
    {
        $rumour = Rumour::fromArray([
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1234567890,
            'kind' => 1,
            'tags' => [],
            'content' => ['key' => 'value'],
        ]);

        $this->assertNotNull($rumour);
        $this->assertSame('{"key":"value"}', (string) $rumour->getContent());
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('malformedRumourProvider')]
    public function testFromArrayReturnsNullForMalformedFields(array $data): void
    {
        $this->assertNull(Rumour::fromArray($data));
    }

    /**
     * @return array<string, mixed>
     */
    private static function validRumourArray(): array
    {
        return [
            'pubkey' => str_repeat('a', 64),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => 'hello',
        ];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function malformedRumourProvider(): iterable
    {
        yield 'pubkey not a string' => [[...self::validRumourArray(), 'pubkey' => 123]];
        yield 'pubkey not valid hex' => [[...self::validRumourArray(), 'pubkey' => 'zz']];
        yield 'created_at not an int' => [[...self::validRumourArray(), 'created_at' => '1700000000']];
        yield 'created_at negative' => [[...self::validRumourArray(), 'created_at' => -1]];
        yield 'kind not an int' => [[...self::validRumourArray(), 'kind' => '1']];
        yield 'kind above protocol maximum' => [[...self::validRumourArray(), 'kind' => 70000]];
        yield 'tags not an array' => [[...self::validRumourArray(), 'tags' => 'nope']];
        yield 'content not encodable as json' => [[...self::validRumourArray(), 'content' => ["\xB1"]]];
    }

    public function testWithTagsReturnsNewRumourWithReplacedTags(): void
    {
        $newTags = new TagCollection([Tag::hashtag('nostr')]);
        $updated = $this->rumour->withTags($newTags);

        $this->assertTrue($this->rumour->getTags()->isEmpty());
        $this->assertTrue($updated->getTags()->equals($newTags));
    }

    public function testWithTagsPreservesOtherFields(): void
    {
        $updated = $this->rumour->withTags(new TagCollection([Tag::hashtag('nostr')]));

        $this->assertTrue($updated->getPubkey()->equals($this->rumour->getPubkey()));
        $this->assertTrue($updated->getKind()->equals($this->rumour->getKind()));
        $this->assertTrue($updated->getContent()->equals($this->rumour->getContent()));
        $this->assertTrue($updated->getCreatedAt()->equals($this->rumour->getCreatedAt()));
    }

    public function testIsReplyReturnsFalseForEventWithNoEventTags(): void
    {
        $this->assertFalse($this->rumour->isReply());
    }

    public function testIsReplyReturnsTrueForEventWithEventTagsNoMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForRootMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef', '', 'root']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForReplyMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef', '', 'reply']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsFalseForOnlyMentionMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'mention', [['e', '1234567890abcdef', '', 'mention']]);

        $this->assertFalse($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForMixedMentionAndRootMarkers(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [
            ['e', '1234567890abcdef', '', 'root'],
            ['e', 'fedcba0987654321', '', 'mention'],
        ]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForEmptyMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef', 'wss://relay.example.com', '']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForCommentKind(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::COMMENT, 'comment', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', 'wss://relay.com', str_repeat('a', 64)],
        ]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsFalseForRepostWithEventTags(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::REPOST, '', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'],
        ]);

        $this->assertFalse($rumour->isReply());
    }

    public function testIsRepostReturnsTrueForRepostKind(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::REPOST, '', []);

        $this->assertTrue($rumour->isRepost());
    }

    public function testIsRepostReturnsTrueForGenericRepostKind(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::GENERIC_REPOST, '', []);

        $this->assertTrue($rumour->isRepost());
    }

    public function testIsRepostReturnsFalseForTextNote(): void
    {
        $this->assertFalse($this->rumour->isRepost());
    }

    public function testIsDeletionReturnsTrueForKind5(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::EVENT_DELETION, '', [['e', str_repeat('a', 64)]]);

        $this->assertTrue($rumour->isDeletion());
    }

    public function testIsDeletionReturnsFalseForTextNote(): void
    {
        $this->assertFalse($this->rumour->isDeletion());
    }

    public function testIsExpiredReturnsFalseWithNoExpirationTag(): void
    {
        $this->assertFalse($this->rumour->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpired(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', (string) (time() - 3600)]]);

        $this->assertTrue($rumour->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenNotYetExpired(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', (string) (time() + 3600)]]);

        $this->assertFalse($rumour->isExpired());
    }

    public function testIsExpiredReturnsFalseForNegativeExpirationValue(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '-1']]);

        $this->assertFalse($rumour->isExpired());
    }

    public function testIsProtectedReturnsTrueWithProtectedTag(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['-']]);

        $this->assertTrue($rumour->isProtected());
    }

    public function testIsProtectedReturnsFalseWithoutProtectedTag(): void
    {
        $this->assertFalse($this->rumour->isProtected());
    }

    public function testGetPublishedAtReturnsTimestampWhenTagExists(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['published_at', '1700000000']]);

        $publishedAt = $rumour->getPublishedAt();
        $this->assertNotNull($publishedAt);
        $this->assertSame(1700000000, $publishedAt->toInt());
    }

    public function testGetPublishedAtReturnsNullWhenTagValueNegative(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['published_at', '-1']]);

        $this->assertNull($rumour->getPublishedAt());
    }

    public function testGetPublishedAtReturnsNullWhenNoTag(): void
    {
        $this->assertNull($this->rumour->getPublishedAt());
    }

    private function recreateId(string $content): EventId
    {
        return (new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(1),
            new TagCollection(),
            EventContent::fromString($content),
        ))->getId();
    }

    /**
     * @param list<list<string>> $tagArrays
     */
    private function rumourWithKindAndContent(int $kind, string $content, array $tagArrays): Rumour
    {
        $tags = array_map(Tag::fromArray(...), $tagArrays);

        return new Rumour(
            PublicKey::fromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt($kind),
            new TagCollection($tags),
            EventContent::fromString($content)
        );
    }
}
