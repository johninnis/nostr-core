<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    private KeyPair $keyPair;
    private Rumour $rumour;
    private Event $event;

    protected function setUp(): void
    {
        $this->keyPair = KeyMother::alice();

        $this->rumour = new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('Hello Nostr!')
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
            $this->rumour->withTags(new TagCollection([Tag::hashtag('changed')])),
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
        $this->assertSame($this->rumour->isExpired(), $this->event->isExpired());
        $this->assertSame($this->rumour->isProtected(), $this->event->isProtected());
        $this->assertSame($this->rumour->getPublishedAt(), $this->event->getPublishedAt());
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

    public function testRoundTripsThroughArray(): void
    {
        $recreated = Event::fromArray($this->event->toArray());

        $this->assertNotNull($recreated);
        $this->assertSame($this->event->toArray(), $recreated->toArray());
    }

    public function testFromArrayRequiresId(): void
    {
        $array = $this->event->toArray();
        unset($array['id']);

        $this->assertNull(Event::fromArray($array));
    }

    public function testFromArrayRejectsMissingSignature(): void
    {
        $array = $this->event->toArray();
        unset($array['sig']);

        $this->assertNull(Event::fromArray($array));
    }

    public function testFromArrayRejectsEmptySignature(): void
    {
        $array = $this->event->toArray();
        $array['sig'] = '';

        $this->assertNull(Event::fromArray($array));
    }

    public function testFromArrayRejectsMalformedCoreFields(): void
    {
        $array = $this->event->toArray();
        $array['pubkey'] = 'zz';

        $this->assertNull(Event::fromArray($array));
    }

    public function testFromWireParsesAnArrayPayload(): void
    {
        $array = $this->event->toArray();

        $this->assertEquals(Event::fromArray($array), Event::fromWire($array));
    }

    #[DataProvider('nonArrayWireValues')]
    public function testFromWireReturnsNullForNonArrayPayload(mixed $value): void
    {
        $this->assertNull(Event::fromWire($value));
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

    public function testFromJsonRetainsTheVerbatimJson(): void
    {
        $json = json_encode($this->event->toArray(), JSON_THROW_ON_ERROR);

        $parsed = Event::fromJson($json);

        $this->assertNotNull($parsed);
        $this->assertSame($json, $parsed->getRawJson());
        $this->assertSame($this->event->getId()->toHex(), $parsed->getId()->toHex());
    }

    public function testFromArrayLeavesRawJsonNull(): void
    {
        $parsed = Event::fromArray($this->event->toArray());

        $this->assertNotNull($parsed);
        $this->assertNull($parsed->getRawJson());
    }

    public function testWithRawJsonEncodesTheEvent(): void
    {
        $parsed = Event::fromArray($this->event->toArray());
        $this->assertNotNull($parsed);
        $parsed = $parsed->withRawJson();

        $this->assertSame(
            json_encode($this->event->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS),
            $parsed->getRawJson()
        );
    }

    public function testWithRawJsonReturnsSameInstanceWhenRawJsonPresent(): void
    {
        $parsed = Event::fromJson(json_encode($this->event->toArray(), JSON_THROW_ON_ERROR));

        $this->assertNotNull($parsed);
        $this->assertSame($parsed, $parsed->withRawJson());
    }

    public function testToJsonReturnsRawJsonWhenPresent(): void
    {
        $json = json_encode($this->event->toArray(), JSON_THROW_ON_ERROR);

        $parsed = Event::fromJson($json);

        $this->assertNotNull($parsed);
        $this->assertSame($json, $parsed->toJson());
    }

    public function testToJsonEncodesWhenRawJsonAbsent(): void
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
    public function testFromArrayReturnsNullForMalformedFields(array $data): void
    {
        $this->assertNull(Event::fromArray($data));
    }

    public function testFromArrayParsesTheValidBaselineUsedByMalformedCases(): void
    {
        $this->assertNotNull(Event::fromArray(self::validSignedEventArray()));
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
