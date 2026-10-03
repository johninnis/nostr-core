<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Failure\RumourParseFailure;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class RumourTest extends TestCase
{
    private KeyPair $keyPair;
    private Rumour $rumour;

    protected function setUp(): void
    {
        $this->keyPair = KeyMother::alice();

        $this->rumour = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr!'),
            new TagCollection(),
            Timestamp::now(),
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
        $first = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::TEXT_NOTE), EventContent::fromString('test'), new TagCollection(), Timestamp::fromInt(1234567890));
        $second = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::TEXT_NOTE), EventContent::fromString('test'), new TagCollection(), Timestamp::fromInt(1234567890));

        $this->assertTrue($first->getId()->equals($second->getId()));
    }

    public function testGetIdHashesAControlCharacterInItsJsonEncoderEscapedForm(): void
    {
        $rumour = Rumour::draft(
            PublicKey::tryFromHex('79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(1),
            EventContent::fromString("a\u{1}b"),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        );

        $this->assertSame('0f8048f56ac7b672dcce8e44bf37294ed15345c228c24afe07339ddf85772a22', $rumour->getId()->toHex());
    }

    public function testDifferentContentProducesDifferentIds(): void
    {
        $first = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::TEXT_NOTE), EventContent::fromString('test1'), new TagCollection(), Timestamp::fromInt(1234567890));
        $second = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::TEXT_NOTE), EventContent::fromString('test2'), new TagCollection(), Timestamp::fromInt(1234567890));

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

    public function testTryFromFieldsParsesTheUnsignedCore(): void
    {
        $rumour = Rumour::tryFromFields([
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => 'Hello',
        ]);

        $this->assertInstanceOf(Rumour::class, $rumour);
        $this->assertSame('Hello', (string) $rumour->getContent());
    }

    public function testTryFromFieldsDoesNotReadAStatedId(): void
    {
        $rumour = Rumour::tryFromFields([...self::unsignedFields(), 'id' => str_repeat('f', 64)]);

        $this->assertTrue($rumour?->getId()->equals(self::validRumourId()));
    }

    public function testTryFromFieldsReturnsNullForFieldsThatAreNotAnUnsignedEvent(): void
    {
        $this->assertNull(Rumour::tryFromFields([...self::unsignedFields(), 'kind' => 'one']));
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArrayAsMalformed(): void
    {
        $this->assertSame(RumourParseFailure::Malformed, Rumour::tryFromArray('rumour'));
    }

    public function testTryFromArrayRefusesARumourWithNoIdAsMalformed(): void
    {
        $this->assertSame(RumourParseFailure::Malformed, Rumour::tryFromArray(self::unsignedFields()));
    }

    public function testTryFromArrayAcceptsAStatedIdThatMatchesItsFields(): void
    {
        $rumour = Rumour::tryFromArray(self::validRumourArray());

        $this->assertInstanceOf(Rumour::class, $rumour);
    }

    public function testTryFromArrayRefusesAStatedIdThatDoesNotMatchItsFields(): void
    {
        $this->assertSame(
            RumourParseFailure::IdMismatch,
            Rumour::tryFromArray([...self::validRumourArray(), 'id' => str_repeat('f', 64)]),
        );
    }

    public function testTryFromArrayRefusesAStatedIdInUppercaseHex(): void
    {
        $this->assertSame(
            RumourParseFailure::IdMismatch,
            Rumour::tryFromArray([...self::validRumourArray(), 'id' => strtoupper(self::validRumourId()->toHex())]),
        );
    }

    public function testTryFromArrayRefusesAStatedIdThatIsNotAString(): void
    {
        $this->assertSame(RumourParseFailure::IdMismatch, Rumour::tryFromArray([...self::validRumourArray(), 'id' => null]));
    }

    public function testTryFromArrayIgnoresTheSignatureField(): void
    {
        $rumour = Rumour::tryFromArray([...self::validRumourArray(), 'sig' => str_repeat('a', 128)]);

        $this->assertInstanceOf(Rumour::class, $rumour);
    }

    public function testTryFromArrayReportsMissingRequiredFieldsAsMalformed(): void
    {
        $this->assertSame(RumourParseFailure::Malformed, Rumour::tryFromArray([
            'pubkey' => $this->keyPair->getPublicKey()->toHex(),
            'created_at' => 1700000000,
        ]));
    }

    public function testTryFromArrayReportsInvalidUtf8ContentAsMalformed(): void
    {
        $this->assertSame(RumourParseFailure::Malformed, Rumour::tryFromArray([
            'pubkey' => str_repeat('a', 64),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => "bad\xff\xfeutf8",
        ]));
    }

    public function testTryFromArrayReportsAMalformedRumourAsMalformedEvenWithAMismatchedId(): void
    {
        $this->assertSame(RumourParseFailure::Malformed, Rumour::tryFromArray([...self::validRumourArray(), 'kind' => 'one', 'id' => str_repeat('f', 64)]));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('malformedRumourProvider')]
    public function testTryFromArrayReportsMalformedFields(array $data): void
    {
        $this->assertSame(RumourParseFailure::Malformed, Rumour::tryFromArray($data));
    }

    /**
     * @return array<string, mixed>
     */
    private static function unsignedFields(): array
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
     * @return array<string, mixed>
     */
    private static function validRumourArray(): array
    {
        return [...self::unsignedFields(), 'id' => self::validRumourId()->toHex()];
    }

    private static function validRumourId(): EventId
    {
        return Rumour::draft(
            PublicKey::tryFromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(1),
            EventContent::fromString('hello'),
            createdAt: Timestamp::fromInt(1700000000),
        )->getId();
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
        yield 'content an object' => [[...self::validRumourArray(), 'content' => ['key' => 'value']]];
        yield 'content a list' => [[...self::validRumourArray(), 'content' => ['hello']]];
        yield 'content an int' => [[...self::validRumourArray(), 'content' => 42]];
        yield 'content a float' => [[...self::validRumourArray(), 'content' => 1.5]];
        yield 'content a bool' => [[...self::validRumourArray(), 'content' => true]];
        yield 'content null' => [[...self::validRumourArray(), 'content' => null]];
        yield 'content not encodable as json' => [[...self::validRumourArray(), 'content' => ["\xB1"]]];
    }

    public function testWithTagsReturnsNewRumourWithReplacedTags(): void
    {
        $newTags = new TagCollection([Tag::hashtag(Hashtag::fromString('nostr'))]);
        $updated = $this->rumour->withTags($newTags);

        $this->assertTrue($this->rumour->getTags()->isEmpty());
        $this->assertTrue($updated->getTags()->equals($newTags));
    }

    public function testWithTagsPreservesOtherFields(): void
    {
        $updated = $this->rumour->withTags(new TagCollection([Tag::hashtag(Hashtag::fromString('nostr'))]));

        $this->assertTrue($updated->getPubkey()->equals($this->rumour->getPubkey()));
        $this->assertTrue($updated->getKind()->equals($this->rumour->getKind()));
        $this->assertTrue($updated->getContent()->equals($this->rumour->getContent()));
        $this->assertTrue($updated->getCreatedAt()->equals($this->rumour->getCreatedAt()));
    }

    public function testWithCreatedAtRestampsTheRumour(): void
    {
        $restamped = $this->rumour->withCreatedAt(Timestamp::fromInt(1600000000));

        $this->assertSame(1600000000, $restamped->getCreatedAt()->toInt());
    }

    public function testWithCreatedAtPreservesOtherFields(): void
    {
        $restamped = $this->rumour->withCreatedAt(Timestamp::fromInt(1600000000));

        $this->assertSame(
            [$this->rumour->getPubkey()->toHex(), $this->rumour->getKind()->toInt(), $this->rumour->getTags()->toJsonArray(), (string) $this->rumour->getContent()],
            [$restamped->getPubkey()->toHex(), $restamped->getKind()->toInt(), $restamped->getTags()->toJsonArray(), (string) $restamped->getContent()],
        );
    }

    public function testWithCreatedAtGivesTheRumourTheIdOfItsNewInstant(): void
    {
        $instant = Timestamp::fromInt(1600000000);
        $restamped = $this->rumour->withCreatedAt($instant);
        $drafted = Rumour::draft($this->rumour->getPubkey(), $this->rumour->getKind(), $this->rumour->getContent(), $this->rumour->getTags(), $instant);

        $this->assertTrue($restamped->getId()->equals($drafted->getId()));
    }

    public function testDraftWritesAnEmptyDTagForAnAddressableKindWithoutOne(): void
    {
        $rumour = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::APPLICATION_SPECIFIC_DATA));

        $this->assertSame([['d', '']], $rumour->getTags()->toJsonArray());
    }

    public function testDraftKeepsTheDTagAnAddressableKindAlreadyCarries(): void
    {
        $rumour = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::FOLLOW_SET),
            tags: new TagCollection([Tag::identifier('friends')]),
        );

        $this->assertSame([['d', 'friends']], $rumour->getTags()->toJsonArray());
    }

    public function testDraftAddsNoDTagForANonAddressableKind(): void
    {
        $rumour = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertTrue($rumour->getTags()->isEmpty());
    }

    public function testWithTagsWritesAnEmptyDTagForAnAddressableKindWithoutOne(): void
    {
        $rumour = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(EventKind::LONGFORM_CONTENT), tags: new TagCollection([Tag::identifier('post')]));

        $this->assertSame([['d', '']], $rumour->withTags(new TagCollection())->getTags()->toJsonArray());
    }

    public function testTryFromFieldsLeavesAParsedAddressableEventWithoutADTagUnchanged(): void
    {
        $rumour = Rumour::tryFromFields([...self::unsignedFields(), 'kind' => EventKind::LONGFORM_CONTENT]);

        $this->assertInstanceOf(Rumour::class, $rumour);
        $this->assertTrue($rumour->getTags()->isEmpty());
    }

    public function testIsReplyReturnsFalseForEventWithNoEventTags(): void
    {
        $this->assertFalse($this->rumour->isReply());
    }

    public function testAMentionMarkedEventTagIsNotAReply(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'mention', [['e', str_repeat('ab', 32), '', 'mention']]);

        $this->assertFalse($rumour->isReply());
    }

    public function testAnEventTagThatDoesNotParseIsNotAReply(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', 'not-a-valid-event-id']]);

        $this->assertFalse($rumour->isReply());
    }

    public function testIsReplyAgreesWithTheReplyChainAnalyser(): void
    {
        $tags = [['e', str_repeat('ab', 32), '', 'mention']];
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'mention', $tags);

        $this->assertSame(
            ReplyChainAnalyser::analyse($rumour->getTags(), $rumour->getKind())->isReply(),
            $rumour->isReply(),
        );
    }

    public function testIsReplyReturnsTrueForEventWithEventTagsNoMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForRootMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', '', 'root']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForReplyMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', '', 'reply']]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsFalseForOnlyMentionMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'mention', [['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', '', 'mention']]);

        $this->assertFalse($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForMixedMentionAndRootMarkers(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [
            ['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', '', 'root'],
            ['e', 'fedcba0987654321', '', 'mention'],
        ]);

        $this->assertTrue($rumour->isReply());
    }

    public function testIsReplyReturnsTrueForEmptyMarker(): void
    {
        $rumour = $this->rumourWithKindAndContent(EventKind::TEXT_NOTE, 'reply', [['e', '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', 'wss://relay.example.com', '']]);

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

    public function testReadsNoClockToAnswerWhetherItHasExpired(): void
    {
        $this->assertFalse(new ReflectionClass(Rumour::class)->hasMethod('isExpired'));
    }

    public function testIsExpiredAtIsTrueWhenTheReferenceIsPastTheExpiry(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '100']]);

        $this->assertTrue($rumour->isExpiredAt(Timestamp::fromInt(101)));
    }

    public function testIsExpiredAtIsTrueAtTheExpiryInstant(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '100']]);

        $this->assertTrue($rumour->isExpiredAt(Timestamp::fromInt(100)));
    }

    public function testIsExpiredAtIsFalseBeforeTheExpiry(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '100']]);

        $this->assertFalse($rumour->isExpiredAt(Timestamp::fromInt(99)));
    }

    public function testAnyStatedExpiryThatHasPassedExpiresTheEvent(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '9999999999'], ['expiration', '100']]);

        $this->assertTrue($rumour->isExpiredAt(Timestamp::fromInt(101)));
    }

    public function testTheAnswerDoesNotDependOnTheOrderTheExpiriesWereWritten(): void
    {
        $reference = Timestamp::fromInt(101);
        $laterFirst = $this->rumourWithKindAndContent(1, 'test', [['expiration', '9999999999'], ['expiration', '100']]);
        $earlierFirst = $this->rumourWithKindAndContent(1, 'test', [['expiration', '100'], ['expiration', '9999999999']]);

        $this->assertSame($laterFirst->isExpiredAt($reference), $earlierFirst->isExpiredAt($reference));
    }

    public function testSeveralFutureExpiriesLeaveTheEventLive(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '8000000000'], ['expiration', '9000000000']]);

        $this->assertFalse($rumour->isExpiredAt(Timestamp::fromInt(101)));
    }

    public function testAnUnreadableExpiryBesideAPassedOneStillExpiresTheEvent(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', 'soon'], ['expiration', '100']]);

        $this->assertTrue($rumour->isExpiredAt(Timestamp::fromInt(101)));
    }

    public function testIsExpiredAtIsFalseWithNoExpirationTag(): void
    {
        $this->assertFalse($this->rumour->isExpiredAt(Timestamp::fromInt(PHP_INT_MAX)));
    }

    public function testIsExpiredAtIsFalseForAnUnparseableExpirationValue(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', 'soon']]);

        $this->assertFalse($rumour->isExpiredAt(Timestamp::fromInt(PHP_INT_MAX)));
    }

    public function testIsExpiredAtIsFalseForANegativeExpirationValue(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['expiration', '-1']]);

        $this->assertFalse($rumour->isExpiredAt(Timestamp::fromInt(PHP_INT_MAX)));
    }

    public function testIsProtectedReturnsTrueWithProtectedTag(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['-']]);

        $this->assertTrue($rumour->isProtected());
    }

    public function testIsProtectedIsFalseForADashTagCarryingAValue(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['-', 'x']]);

        $this->assertFalse($rumour->isProtected());
    }

    public function testIsProtectedIsTrueWhenAnExactDashTagSitsBesideOneCarryingAValue(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['-', 'x'], ['-']]);

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

    public function testGetPublishedAtReturnsNullWhenTagValueNotNumeric(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['published_at', 'yesterday']]);

        $this->assertNull($rumour->getPublishedAt());
    }

    public function testGetPublishedAtReturnsNullWhenTagsDisagree(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['published_at', '1700000000'], ['published_at', '1700000001']]);

        $this->assertNull($rumour->getPublishedAt());
    }

    public function testGetPublishedAtReadsARepeatedTagAsOneClaim(): void
    {
        $rumour = $this->rumourWithKindAndContent(1, 'test', [['published_at', '1700000000'], ['published_at', '1700000000']]);

        $this->assertSame(1700000000, $rumour->getPublishedAt()?->toInt());
    }

    public function testGetPublishedAtReturnsNullWhenNoTag(): void
    {
        $this->assertNull($this->rumour->getPublishedAt());
    }

    /**
     * @param list<list<string>> $tagArrays
     */
    private function rumourWithKindAndContent(int $kind, string $content, array $tagArrays): Rumour
    {
        $tags = array_map(Tag::tryFromArray(...), $tagArrays);

        return Rumour::draft(
            PublicKey::tryFromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt($kind),
            EventContent::fromString($content),
            new TagCollection($tags),
            Timestamp::fromInt(1234567890),
        );
    }

    public function testGetChatRoomIsTheAuthorAndEveryPTaggedPubkeyOnceInOrder(): void
    {
        $author = str_repeat('f', 64);
        $receiver = str_repeat('1', 64);
        $rumour = Rumour::draft(
            PublicKey::tryFromHex($author) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::PRIVATE_MESSAGE),
            tags: new TagCollection(array_map(Tag::fromArray(...), [['p', $receiver], ['p', $author], ['p', $receiver], ['p', 'not-a-key']])),
        );

        $this->assertSame([$receiver, $author], $rumour->getChatRoom()->toHexes());
    }

    public function testGetChatRoomDoesNotDependOnTheOrderOfThePTags(): void
    {
        $author = PublicKey::tryFromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test pubkey');
        $one = new TagCollection([Tag::fromArray(['p', str_repeat('b', 64)]), Tag::fromArray(['p', str_repeat('c', 64)])]);
        $other = new TagCollection([Tag::fromArray(['p', str_repeat('c', 64)]), Tag::fromArray(['p', str_repeat('b', 64)])]);

        $this->assertSame(
            Rumour::draft($author, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), tags: $one)->getChatRoom()->toHexes(),
            Rumour::draft($author, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), tags: $other)->getChatRoom()->toHexes(),
        );
    }

    public function testADraftDefaultsToEmptyContentAndNoTags(): void
    {
        $draft = Rumour::draft(KeyMother::alice()->getPublicKey(), EventKind::fromInt(EventKind::FOLLOW_LIST));

        $this->assertSame(['', 0], [(string) $draft->getContent(), $draft->getTags()->count()]);
    }

    public function testADraftDefaultsItsCreatedAtToNow(): void
    {
        $before = Timestamp::now();

        $draft = Rumour::draft(KeyMother::alice()->getPublicKey(), EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($draft->getCreatedAt()->isBefore($before));
    }

    public function testADraftKeepsTheFieldsItIsGiven(): void
    {
        $draft = Rumour::draft(
            KeyMother::alice()->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('hello'),
            new TagCollection([Tag::fromArray(['t', 'nostr'])]),
            Timestamp::fromInt(1700000000),
        );

        $this->assertSame(
            ['hello', 1, 1700000000],
            [(string) $draft->getContent(), $draft->getTags()->count(), $draft->getCreatedAt()->toInt()],
        );
    }
}
