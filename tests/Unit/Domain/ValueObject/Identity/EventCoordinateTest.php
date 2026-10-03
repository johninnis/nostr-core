<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class EventCoordinateTest extends TestCase
{
    private const string VALID_PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const int VALID_KIND = 30023;
    private const string VALID_IDENTIFIER = 'my-article';
    private const string VALID_RELAY = 'wss://relay.example.com';

    private function createCoordinate(?string $relayHint = null): EventCoordinate
    {
        $coordinate = EventCoordinate::tryFromString(self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER)
            ?? throw new RuntimeException('Failed to create test coordinate');

        return null !== $relayHint ? $coordinate->withRelayHint(RelayUrl::tryFromString($relayHint)) : $coordinate;
    }

    public function testTryFromPartsCreatesValidCoordinate(): void
    {
        $coordinate = $this->createCoordinate();

        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
        $this->assertSame(self::VALID_PUBKEY, $coordinate->getPubkey()->toHex());
        $this->assertSame(self::VALID_IDENTIFIER, $coordinate->getIdentifier());
        $this->assertNull($coordinate->getRelayHint());
    }

    public function testCoordinateExposesItsRelayHint(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $this->assertNotNull($coordinate->getRelayHint());
        $this->assertSame(self::VALID_RELAY, (string) $coordinate->getRelayHint());
    }

    public function testTryFromPartsReturnsNullForNonAddressableKind(): void
    {
        $this->assertNull(EventCoordinate::tryFromString('1:'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER));
    }

    public function testTryFromPartsReturnsNullForInvalidPubkey(): void
    {
        $this->assertNull(EventCoordinate::tryFromString(self::VALID_KIND.':invalid:'.self::VALID_IDENTIFIER));
    }

    public function testTryFromStringAcceptsAnAddressableKindWithAnEmptyIdentifier(): void
    {
        $this->assertSame('', EventCoordinate::tryFromString(self::VALID_KIND.':'.self::VALID_PUBKEY.':')?->getIdentifier());
    }

    // Deliberate: NIP-01 writes a replaceable event's coordinate as <kind>:<pubkey>: with the trailing colon, so an empty identifier is its only form — see ADR-0082
    public function testTryFromStringAcceptsAReplaceableKindWithAnEmptyIdentifier(): void
    {
        $coordinate = EventCoordinate::tryFromString('10002:'.self::VALID_PUBKEY.':');

        $this->assertSame('10002:'.self::VALID_PUBKEY.':', (string) $coordinate);
    }

    public function testTryFromStringRejectsAReplaceableKindWithAnIdentifier(): void
    {
        $this->assertNull(EventCoordinate::tryFromString('10002:'.self::VALID_PUBKEY.':x'));
    }

    public function testTryFromBuildsCoordinateFromValueObjects(): void
    {
        $kind = EventKind::fromInt(self::VALID_KIND);
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $relay = RelayUrl::fromString(self::VALID_RELAY);

        $coordinate = EventCoordinate::tryFrom($kind, $pubkey, self::VALID_IDENTIFIER)?->withRelayHint($relay)
            ?? throw new RuntimeException('Failed to create coordinate');

        $this->assertTrue($coordinate->getKind()->equals($kind));
        $this->assertTrue($coordinate->getPubkey()->equals($pubkey));
        $this->assertSame(self::VALID_IDENTIFIER, $coordinate->getIdentifier());
        $this->assertSame(self::VALID_RELAY, (string) $coordinate->getRelayHint());
    }

    public function testTryFromRefusesAnIdentifierThatIsNotUtf8(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(EventCoordinate::tryFrom(EventKind::fromInt(self::VALID_KIND), $pubkey, "\xff\xfe"));
    }

    public function testTryFromReturnsNullForNonAddressableKind(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(EventCoordinate::tryFrom(EventKind::fromInt(1), $pubkey, self::VALID_IDENTIFIER));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function acceptedKindsAndIdentifiers(): iterable
    {
        yield 'metadata with an empty identifier' => [0, ''];
        yield 'follow list with an empty identifier' => [3, ''];
        yield 'relay list with an empty identifier' => [10002, ''];
        yield 'addressable kind with an empty identifier' => [30023, ''];
        yield 'addressable kind with an identifier' => [30023, 'x'];
    }

    #[DataProvider('acceptedKindsAndIdentifiers')]
    public function testTryFromAcceptsAReplaceableOrAddressableCoordinate(int $kind, string $identifier): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertSame($identifier, EventCoordinate::tryFrom(EventKind::fromInt($kind), $pubkey, $identifier)?->getIdentifier());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function rejectedKindsAndIdentifiers(): iterable
    {
        yield 'relay list with an identifier' => [10002, 'x'];
        yield 'regular kind with an empty identifier' => [1, ''];
        yield 'ephemeral kind with an empty identifier' => [20000, ''];
        yield 'ephemeral kind with an identifier' => [20000, 'x'];
    }

    #[DataProvider('rejectedKindsAndIdentifiers')]
    public function testTryFromRejectsAnyOtherCoordinate(int $kind, string $identifier): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(EventCoordinate::tryFrom(EventKind::fromInt($kind), $pubkey, $identifier));
    }

    public function testTryFromStringParsesValidCoordinate(): void
    {
        $coordinateString = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $coordinate = EventCoordinate::tryFromString($coordinateString)
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
        $this->assertSame(self::VALID_PUBKEY, $coordinate->getPubkey()->toHex());
        $this->assertSame(self::VALID_IDENTIFIER, $coordinate->getIdentifier());
    }

    public function testTryFromStringHandlesIdentifierWithColons(): void
    {
        $coordinateString = self::VALID_KIND.':'.self::VALID_PUBKEY.':part1:part2:part3';
        $coordinate = EventCoordinate::tryFromString($coordinateString)
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertSame('part1:part2:part3', $coordinate->getIdentifier());
    }

    public function testTryFromStringReturnsNullForFewerThanThreeParts(): void
    {
        $this->assertNull(EventCoordinate::tryFromString('30023:'.self::VALID_PUBKEY));
    }

    public function testTryFromStringReturnsNullForNonNumericKind(): void
    {
        $this->assertNull(EventCoordinate::tryFromString('30023x:'.self::VALID_PUBKEY.':my-article'));
        $this->assertNull(EventCoordinate::tryFromString('abc:'.self::VALID_PUBKEY.':my-article'));
    }

    public function testTryFromStringWithRelayHint(): void
    {
        $coordinateString = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $coordinate = EventCoordinate::tryFromString($coordinateString, self::VALID_RELAY)
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertNotNull($coordinate->getRelayHint());
    }

    public function testTryFromStringIgnoresAnEmptyRelayHint(): void
    {
        $coordinate = EventCoordinate::tryFromString(self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, '')
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertNull($coordinate->getRelayHint());
    }

    public function testHasOneReaderOfACoordinateAndItsHint(): void
    {
        $this->assertFalse(new ReflectionClass(EventCoordinate::class)->hasMethod('tryFromATag'));
    }

    public function testToStringReturnsCoordinateFormat(): void
    {
        $coordinate = $this->createCoordinate();

        $expected = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $this->assertSame($expected, (string) $coordinate);
    }

    public function testToATagWritesTheCoordinateWithoutARelayHint(): void
    {
        $this->assertSame(
            ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER],
            $this->createCoordinate()->toATag()->toArray(),
        );
    }

    public function testToATagWritesTheRelayHintAfterTheCoordinate(): void
    {
        $this->assertSame(
            ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, self::VALID_RELAY],
            $this->createCoordinate(self::VALID_RELAY)->toATag()->toArray(),
        );
    }

    public function testWithRelayHintReturnsNewInstance(): void
    {
        $coordinate = $this->createCoordinate();
        $relayUrl = RelayUrl::tryFromString(self::VALID_RELAY);

        $withHint = $coordinate->withRelayHint($relayUrl);

        $this->assertNull($coordinate->getRelayHint());
        $this->assertNotNull($withHint->getRelayHint());
        $this->assertSame(self::VALID_RELAY, (string) $withHint->getRelayHint());
    }

    public function testEqualsReturnsTrueForSameCoordinate(): void
    {
        $coordinate1 = $this->createCoordinate();
        $coordinate2 = $this->createCoordinate();

        $this->assertTrue($coordinate1->equals($coordinate2));
    }

    public function testEqualsReturnsFalseForDifferentIdentifier(): void
    {
        $coordinate1 = EventCoordinate::tryFromString(self::VALID_KIND.':'.self::VALID_PUBKEY.':article-one')
            ?? throw new RuntimeException('Failed to create test coordinate');
        $coordinate2 = EventCoordinate::tryFromString(self::VALID_KIND.':'.self::VALID_PUBKEY.':article-two')
            ?? throw new RuntimeException('Failed to create test coordinate');

        $this->assertFalse($coordinate1->equals($coordinate2));
    }

    public function testEqualsIgnoresRelayHintByDefault(): void
    {
        $coordinate1 = $this->createCoordinate();
        $coordinate2 = $this->createCoordinate(self::VALID_RELAY);

        $this->assertTrue($coordinate1->equals($coordinate2));
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $coordinate = $this->createCoordinate();

        $array = $coordinate->toArray();
        $this->assertSame(self::VALID_KIND, $array['kind']);
        $this->assertSame(self::VALID_PUBKEY, $array['pubkey']);
        $this->assertSame(self::VALID_IDENTIFIER, $array['identifier']);
        $this->assertArrayNotHasKey('relay_hint', $array);
    }

    public function testToArrayIncludesRelayHintWhenPresent(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $array = $coordinate->toArray();
        $this->assertSame(self::VALID_RELAY, $array['relay_hint']);
    }

    public function testTryFromArrayCreatesValidCoordinate(): void
    {
        $data = [
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
        ];

        $coordinate = EventCoordinate::tryFromArray($data);

        $this->assertNotNull($coordinate);
        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
    }

    public function testTryFromArrayWithRelayHint(): void
    {
        $data = [
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
            'relay_hint' => self::VALID_RELAY,
        ];

        $coordinate = EventCoordinate::tryFromArray($data);

        $this->assertNotNull($coordinate);
        $this->assertNotNull($coordinate->getRelayHint());
    }

    public function testTryFromArrayReturnsNullForMissingKind(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
        ]));
    }

    public function testTryFromArrayReturnsNullForMissingPubkey(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'kind' => self::VALID_KIND,
            'identifier' => self::VALID_IDENTIFIER,
        ]));
    }

    public function testTryFromArrayReturnsNullForMissingIdentifier(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
        ]));
    }

    public function testTryFromArrayReturnsNullForNonStringPubkey(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'kind' => self::VALID_KIND,
            'pubkey' => 12345,
            'identifier' => self::VALID_IDENTIFIER,
        ]));
    }

    public function testTryFromArrayReturnsNullForNonStringIdentifier(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => ['nested'],
        ]));
    }

    public function testTryFromArrayReturnsNullForNonIntKind(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'kind' => '30023',
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
        ]));
    }

    public function testTryFromArrayReturnsNullForNonStringRelayHint(): void
    {
        $this->assertNull(EventCoordinate::tryFromArray([
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
            'relay_hint' => 42,
        ]));
    }

    public function testRoundTripThroughArray(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $recreated = EventCoordinate::tryFromArray($coordinate->toArray())
            ?? throw new RuntimeException('Failed to recreate coordinate from array');

        $this->assertSame($coordinate->toArray(), $recreated->toArray());
    }

    public function testMatchesEventReturnsTrueForMatchingEvent(): void
    {
        $coordinate = $this->createCoordinate();
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY);
        $this->assertNotNull($pubkey);

        $event = EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(self::VALID_KIND),
            EventContent::fromString('test'),
            new TagCollection([Tag::identifier(self::VALID_IDENTIFIER)]),
            Timestamp::now(),
        ));

        $this->assertTrue($coordinate->matchesEvent($event));
    }

    public function testMatchesEventReturnsFalseForWrongKind(): void
    {
        $coordinate = $this->createCoordinate();
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY);
        $this->assertNotNull($pubkey);

        $event = EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(30078),
            EventContent::fromString('test'),
            new TagCollection([Tag::identifier(self::VALID_IDENTIFIER)]),
            Timestamp::now(),
        ));

        $this->assertFalse($coordinate->matchesEvent($event));
    }

    public function testMatchesEventReturnsFalseForWrongIdentifier(): void
    {
        $coordinate = $this->createCoordinate();
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY);
        $this->assertNotNull($pubkey);

        $event = EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(self::VALID_KIND),
            EventContent::fromString('test'),
            new TagCollection([Tag::identifier('other-article')]),
            Timestamp::now(),
        ));

        $this->assertFalse($coordinate->matchesEvent($event));
    }

    public function testMatchesEventMatchesAReplaceableEventByKindAndAuthor(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $coordinate = EventCoordinate::tryFrom(EventKind::fromInt(10002), $pubkey, '') ?? throw new RuntimeException('Invalid test coordinate');

        $event = EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(10002),
            EventContent::fromString(''),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertTrue($coordinate->matchesEvent($event));
    }

    public function testMatchesEventMatchesAnAddressableEventWithoutADTagToAnEmptyIdentifier(): void
    {
        $coordinate = EventCoordinate::tryFromString(self::VALID_KIND.':'.self::VALID_PUBKEY.':') ?? throw new RuntimeException('Invalid test coordinate');

        $this->assertTrue($coordinate->matchesEvent(self::addressableEventWithoutDTag()));
    }

    public function testMatchesEventDoesNotMatchAnAddressableEventWithoutADTagToANonEmptyIdentifier(): void
    {
        $this->assertFalse($this->createCoordinate()->matchesEvent(self::addressableEventWithoutDTag()));
    }

    public function testMatchesEventMatchesNeitherIdentifierOfDTagsThatDisagree(): void
    {
        $event = self::addressableEventWithDTags('other-article', self::VALID_IDENTIFIER);

        $this->assertFalse($this->createCoordinate()->matchesEvent($event));
    }

    public function testTryFromEventReturnsNullWhenDTagsDisagree(): void
    {
        $this->assertNull(EventCoordinate::tryFromEvent(self::addressableEventWithDTags(self::VALID_IDENTIFIER, 'other-article')));
    }

    public function testTryFromEventReadsARepeatedDTagAsOneIdentifier(): void
    {
        $coordinate = EventCoordinate::tryFromEvent(self::addressableEventWithDTags(self::VALID_IDENTIFIER, self::VALID_IDENTIFIER));

        $this->assertSame(self::VALID_IDENTIFIER, $coordinate?->getIdentifier());
    }

    public function testTryFromEventAddressesAnAddressableEventWithoutADTagWithAnEmptyIdentifier(): void
    {
        $coordinate = EventCoordinate::tryFromEvent(self::addressableEventWithoutDTag());

        $this->assertSame(self::VALID_KIND.':'.self::VALID_PUBKEY.':', (string) $coordinate);
    }

    public function testTryFromEventAddressesAnAddressableEventByItsDTag(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $event = EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(self::VALID_KIND),
            tags: new TagCollection([Tag::identifier(self::VALID_IDENTIFIER)]),
        ));

        $this->assertTrue($this->createCoordinate()->equals(EventCoordinate::tryFromEvent($event) ?? throw new RuntimeException('Expected a coordinate')));
    }

    public function testTryFromEventAddressesAReplaceableEventWithAnEmptyIdentifier(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $event = EventMother::fromRumour(Rumour::draft($pubkey, EventKind::fromInt(10002), tags: new TagCollection([Tag::identifier('ignored')])));

        $this->assertSame('10002:'.self::VALID_PUBKEY.':', (string) EventCoordinate::tryFromEvent($event));
    }

    public function testTryFromEventReturnsNullForARegularEvent(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(EventCoordinate::tryFromEvent(EventMother::fromRumour(Rumour::draft($pubkey, EventKind::fromInt(1)))));
    }

    private static function addressableEventWithDTags(string ...$identifiers): Event
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        return EventMother::fromRumour(Rumour::draft(
            $pubkey,
            EventKind::fromInt(self::VALID_KIND),
            tags: new TagCollection(array_map(Tag::identifier(...), array_values($identifiers))),
        ));
    }

    private static function addressableEventWithoutDTag(): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(self::VALID_KIND),
        ));
    }

    public function testTryFromTagReadsTheCoordinateAndItsRelayHint(): void
    {
        $coordinate = EventCoordinate::tryFromTag(Tag::fromArray(['A', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, self::VALID_RELAY]));

        $this->assertSame([self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, self::VALID_RELAY], [(string) $coordinate, (string) $coordinate?->getRelayHint()]);
    }

    public function testTryFromTagRefusesATagWithNoValue(): void
    {
        $this->assertNull(EventCoordinate::tryFromTag(Tag::fromArray(['a'])));
    }
}
