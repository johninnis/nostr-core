<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EventTest extends TestCase
{
    private const int LARGE_CONTENT_BYTES = 4 * 1024 * 1024;

    private KeyPair $keyPair;
    private Rumour $rumour;
    private Event $event;

    protected function setUp(): void
    {
        $this->keyPair = KeyMother::alice();

        $this->rumour = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr!'),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        );

        $this->event = $this->rumour->sign($this->keyPair, FakeSignatureService::accepting());
    }

    public function testEventCarriesTheRumour(): void
    {
        $this->assertSame($this->rumour, $this->event->getRumour());
    }

    public function testDelegatesCoreReadsToTheRumour(): void
    {
        $this->assertTrue($this->event->getPubkey()->equals($this->keyPair->getPublicKey()));
        $this->assertTrue($this->event->getKind()->is(EventKind::TEXT_NOTE));
        $this->assertSame('Hello Nostr!', (string) $this->event->getContent());
        $this->assertTrue($this->event->getCreatedAt()->equals($this->rumour->getCreatedAt()));
        $this->assertTrue($this->event->getTags()->equals($this->rumour->getTags()));
    }

    public function testGetIdReturnsTheStoredSignedId(): void
    {
        $this->assertTrue($this->event->getId()->equals($this->rumour->getId()));
    }

    public function testGetSignatureIsNeverNull(): void
    {
        $this->assertSame(FakeSignatureService::accepting()->sign($this->keyPair->getPrivateKey(), '')->toHex(), $this->event->getSignature()->toHex());
    }

    public function testVerifyReturnsTrueForAcceptingService(): void
    {
        $this->assertTrue($this->event->verify(FakeSignatureService::accepting()));
    }

    public function testVerifyReturnsFalseForRejectingService(): void
    {
        $this->assertFalse($this->event->verify(FakeSignatureService::rejecting()));
    }

    public function testVerifyReturnsFalseWhenStoredIdDoesNotMatchContent(): void
    {
        $tampered = new Event(
            $this->rumour->withTags(new TagCollection([Tag::hashtag(Hashtag::fromString('changed'))])),
            $this->event->getId(),
            $this->event->getSignature(),
        );

        $this->assertFalse($tampered->verify(FakeSignatureService::accepting()));
    }

    public function testDelegatesPredicatesToTheRumour(): void
    {
        $this->assertSame($this->rumour->isReply(), $this->event->isReply());
        $this->assertSame($this->rumour->isRepost(), $this->event->isRepost());
        $this->assertSame($this->rumour->isDeletion(), $this->event->isDeletion());
        $this->assertSame($this->rumour->isExpiredAt(Timestamp::fromInt(100)), $this->event->isExpiredAt(Timestamp::fromInt(100)));
        $this->assertSame($this->rumour->isProtected(), $this->event->isProtected());
        $this->assertSame($this->rumour->getPublishedAt(), $this->event->getPublishedAt());
    }

    public function testReadsNoClockToAnswerWhetherItHasExpired(): void
    {
        $this->assertFalse(new ReflectionClass(Event::class)->hasMethod('isExpired'));
    }

    public function testToArrayCarriesStoredIdAndRealSignature(): void
    {
        $array = $this->event->toArray();

        $this->assertSame($this->event->getId()->toHex(), $array['id']);
        $this->assertSame($this->event->getPubkey()->toHex(), $array['pubkey']);
        $this->assertSame($this->event->getCreatedAt()->toInt(), $array['created_at']);
        $this->assertSame($this->event->getKind()->toInt(), $array['kind']);
        $this->assertSame($this->event->getTags()->toJsonArray(), $array['tags']);
        $this->assertSame((string) $this->event->getContent(), $array['content']);
        $this->assertSame($this->event->getSignature()->toHex(), $array['sig']);
    }

    public function testToArrayReadsTheStoredIdWithoutSerialisingTheEventToHashIt(): void
    {
        $large = Event::tryFromArray([...$this->event->toArray(), 'content' => str_repeat('a', self::LARGE_CONTENT_BYTES)]);
        $this->assertNotNull($large);

        $this->assertLessThan(self::LARGE_CONTENT_BYTES, self::peakBytesAllocatedBy(static fn (): array => $large->toArray()));
        $this->assertGreaterThan(self::LARGE_CONTENT_BYTES, self::peakBytesAllocatedBy(static fn (): array => $large->getRumour()->toArray()));
    }

    /**
     * @param callable(): array<string, mixed> $serialise
     */
    private static function peakBytesAllocatedBy(callable $serialise): int
    {
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $serialise();

        return memory_get_peak_usage() - $before;
    }

    public function testRoundTripsThroughArray(): void
    {
        $recreated = Event::tryFromArray($this->event->toArray());

        $this->assertNotNull($recreated);
        $this->assertSame($this->event->toArray(), $recreated->toArray());
    }

    public function testTryFromArrayRequiresId(): void
    {
        $array = $this->event->toArray();
        unset($array['id']);

        $this->assertNull(Event::tryFromArray($array));
    }

    public function testTryFromArrayKeepsAStatedIdThatDoesNotMatchItsFieldsForVerifyToRefuse(): void
    {
        $parsed = Event::tryFromArray([...$this->event->toArray(), 'id' => str_repeat('f', 64)]);

        $this->assertNotNull($parsed);
        $this->assertFalse($parsed->verify(FakeSignatureService::accepting()));
    }

    public function testTryFromArrayRejectsMissingSignature(): void
    {
        $array = $this->event->toArray();
        unset($array['sig']);

        $this->assertNull(Event::tryFromArray($array));
    }

    public function testTryFromArrayRejectsEmptySignature(): void
    {
        $array = $this->event->toArray();
        $array['sig'] = '';

        $this->assertNull(Event::tryFromArray($array));
    }

    public function testTryFromArrayRejectsMalformedCoreFields(): void
    {
        $array = $this->event->toArray();
        $array['pubkey'] = 'zz';

        $this->assertNull(Event::tryFromArray($array));
    }

    public function testTryFromArrayParsesAnArrayPayload(): void
    {
        $array = $this->event->toArray();

        $this->assertEquals(Event::tryFromArray($array), Event::tryFromArray($array));
    }

    #[DataProvider('nonArrayWireValues')]
    public function testTryFromArrayReturnsNullForNonArrayPayload(mixed $value): void
    {
        $this->assertNull(Event::tryFromArray($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonArrayWireValues(): iterable
    {
        yield 'string' => ['not-an-event'];
        yield 'int' => [42];
        yield 'bool' => [true];
        yield 'null' => [null];
    }

    public function testToJsonDropsAKeyTheInputCarriedThatTheEventDoesNotHave(): void
    {
        $parsed = Event::tryFromJson(json_encode([...$this->event->toArray(), 'evil' => 'payload'], JSON_THROW_ON_ERROR));

        $this->assertNotNull($parsed);
        $this->assertStringNotContainsString('evil', $parsed->toJson());
    }

    public function testToJsonIsTheCanonicalEncodingOfTheFieldsNotTheInputBytes(): void
    {
        $spaced = json_encode($this->event->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $parsed = Event::tryFromJson($spaced);

        $this->assertNotNull($parsed);
        $this->assertSame($this->event->toJson(), $parsed->toJson());
    }

    public function testTryFromJsonReturnsNullWhenContentIsNotAString(): void
    {
        $this->assertNull(Event::tryFromJson(json_encode([...$this->event->toArray(), 'content' => ['name' => 'alice']], JSON_THROW_ON_ERROR)));
    }

    /**
     * @param list<list<string>> $tags
     */
    #[DataProvider('tagsWrittenAsAnObject')]
    public function testTryFromJsonRefusesTagsWrittenAsAnObject(array $tags, string $writtenTags): void
    {
        $this->assertNull(Event::tryFromJson(self::withTagsWrittenAs($this->signedWithTags($tags), $tags, $writtenTags)));
    }

    /**
     * @return iterable<string, array{list<list<string>>, string}>
     */
    public static function tagsWrittenAsAnObject(): iterable
    {
        yield 'the tag list as an empty object' => [[], '{}'];
        yield 'the tag list as an object keyed by position' => [[['t', 'nostr']], '{"0":["t","nostr"]}'];
        yield 'a tag as an object keyed by position' => [[['t', 'nostr']], '[{"0":"t","1":"nostr"}]'];
        yield 'the tag list as an object with named keys' => [[['t', 'nostr']], '{"x":["t","nostr"]}'];
        yield 'a tag as an object with named keys' => [[['t', 'nostr']], '[{"name":"t","value":"nostr"}]'];
    }

    /**
     * @param list<list<string>> $tags
     */
    private function signedWithTags(array $tags): Event
    {
        return Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr!'),
            new TagCollection(array_map(Tag::fromArray(...), $tags)),
            Timestamp::fromInt(1700000000),
        )->sign($this->keyPair, FakeSignatureService::accepting());
    }

    /**
     * @param list<list<string>> $tags
     */
    private static function withTagsWrittenAs(Event $event, array $tags, string $writtenTags): string
    {
        return str_replace('"tags":'.json_encode($tags, JSON_THROW_ON_ERROR), '"tags":'.$writtenTags, $event->toJson());
    }

    public function testToJsonDecodesToToArray(): void
    {
        $decoded = json_decode($this->event->toJson(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($this->event->toArray(), $decoded);
    }

    public function testToStringReturnsEventId(): void
    {
        $this->assertSame($this->event->getId()->toHex(), (string) $this->event);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('malformedSignedEventProvider')]
    public function testTryFromArrayReturnsNullForMalformedFields(array $data): void
    {
        $this->assertNull(Event::tryFromArray($data));
    }

    public function testTryFromArrayParsesTheValidBaselineUsedByMalformedCases(): void
    {
        $this->assertNotNull(Event::tryFromArray(self::validSignedEventArray()));
    }

    /**
     * @return array<string, mixed>
     */
    private static function validSignedEventArray(): array
    {
        return [
            'id' => str_repeat('a', 64),
            'pubkey' => str_repeat('a', 64),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => 'hello',
            'sig' => str_repeat('a', 128),
        ];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function malformedSignedEventProvider(): iterable
    {
        yield 'pubkey not valid hex' => [[...self::validSignedEventArray(), 'pubkey' => 'zz']];
        yield 'created_at negative' => [[...self::validSignedEventArray(), 'created_at' => -1]];
        yield 'kind above protocol maximum' => [[...self::validSignedEventArray(), 'kind' => 70000]];
        yield 'id missing' => [self::arrayWithout('id')];
        yield 'id not a string' => [[...self::validSignedEventArray(), 'id' => 123]];
        yield 'id not valid hex' => [[...self::validSignedEventArray(), 'id' => 'zz']];
        yield 'sig missing' => [self::arrayWithout('sig')];
        yield 'sig empty' => [[...self::validSignedEventArray(), 'sig' => '']];
        yield 'sig not a string' => [[...self::validSignedEventArray(), 'sig' => 123]];
        yield 'sig not valid hex' => [[...self::validSignedEventArray(), 'sig' => 'zz']];
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayWithout(string $key): array
    {
        $array = self::validSignedEventArray();
        unset($array[$key]);

        return $array;
    }
}
