<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventCoordinateTest extends TestCase
{
    private const string VALID_PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const int VALID_KIND = 30023;
    private const string VALID_IDENTIFIER = 'my-article';
    private const string VALID_RELAY = 'wss://relay.example.com';

    private function createCoordinate(?string $relayHint = null): EventCoordinate
    {
        $coordinate = EventCoordinate::tryFromParts(self::VALID_KIND, self::VALID_PUBKEY, self::VALID_IDENTIFIER)
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

    public function testTryFromPartsReturnsNullForNonParameterisedReplaceableKind(): void
    {
        $this->assertNull(EventCoordinate::tryFromParts(1, self::VALID_PUBKEY, self::VALID_IDENTIFIER));
    }

    public function testTryFromPartsReturnsNullForInvalidPubkey(): void
    {
        $this->assertNull(EventCoordinate::tryFromParts(self::VALID_KIND, 'invalid', self::VALID_IDENTIFIER));
    }

    public function testTryFromPartsReturnsNullForEmptyIdentifier(): void
    {
        $this->assertNull(EventCoordinate::tryFromParts(self::VALID_KIND, self::VALID_PUBKEY, ''));
    }

    public function testTryFromBuildsCoordinateFromValueObjects(): void
    {
        $kind = EventKind::fromInt(self::VALID_KIND);
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $relay = RelayUrl::tryFromString(self::VALID_RELAY) ?? throw new RuntimeException('Invalid test relay');

        $coordinate = EventCoordinate::tryFrom($kind, $pubkey, self::VALID_IDENTIFIER)?->withRelayHint($relay)
            ?? throw new RuntimeException('Failed to create coordinate');

        $this->assertTrue($coordinate->getKind()->equals($kind));
        $this->assertTrue($coordinate->getPubkey()->equals($pubkey));
        $this->assertSame(self::VALID_IDENTIFIER, $coordinate->getIdentifier());
        $this->assertSame(self::VALID_RELAY, (string) $coordinate->getRelayHint());
    }

    public function testTryFromReturnsNullForNonParameterisedReplaceableKind(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(EventCoordinate::tryFrom(EventKind::fromInt(1), $pubkey, self::VALID_IDENTIFIER));
    }

    public function testTryFromReturnsNullForEmptyIdentifier(): void
    {
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(EventCoordinate::tryFrom(EventKind::fromInt(self::VALID_KIND), $pubkey, ''));
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

    public function testTryFromATagParsesValidTag(): void
    {
        $tag = ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER];
        $coordinate = EventCoordinate::tryFromATag($tag)
            ?? throw new RuntimeException('Failed to parse a-tag');

        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
    }

    public function testTryFromATagWithRelayHint(): void
    {
        $tag = ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, self::VALID_RELAY];
        $coordinate = EventCoordinate::tryFromATag($tag)
            ?? throw new RuntimeException('Failed to parse a-tag');

        $this->assertNotNull($coordinate->getRelayHint());
    }

    public function testTryFromATagReturnsNullForNonATag(): void
    {
        $this->assertNull(EventCoordinate::tryFromATag(['p', self::VALID_PUBKEY]));
    }

    public function testTryFromATagReturnsNullForMissingValue(): void
    {
        $this->assertNull(EventCoordinate::tryFromATag(['a']));
    }

    public function testTryFromATagIgnoresEmptyRelayHint(): void
    {
        $tag = ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, ''];
        $coordinate = EventCoordinate::tryFromATag($tag)
            ?? throw new RuntimeException('Failed to parse a-tag');

        $this->assertNull($coordinate->getRelayHint());
    }

    public function testToStringReturnsCoordinateFormat(): void
    {
        $coordinate = $this->createCoordinate();

        $expected = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $this->assertSame($expected, (string) $coordinate);
    }

    public function testToATagReturnsArrayWithoutRelayHint(): void
    {
        $coordinate = $this->createCoordinate();

        $tag = $coordinate->toATag();
        $this->assertSame('a', $tag[0]);
        $this->assertSame(self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, $tag[1]);
        $this->assertCount(2, $tag);
    }

    public function testToATagReturnsArrayWithRelayHint(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $tag = $coordinate->toATag();
        $this->assertCount(3, $tag);
        $this->assertSame(self::VALID_RELAY, $tag[2]);
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
        $coordinate1 = EventCoordinate::tryFromParts(self::VALID_KIND, self::VALID_PUBKEY, 'article-one')
            ?? throw new RuntimeException('Failed to create test coordinate');
        $coordinate2 = EventCoordinate::tryFromParts(self::VALID_KIND, self::VALID_PUBKEY, 'article-two')
            ?? throw new RuntimeException('Failed to create test coordinate');

        $this->assertFalse($coordinate1->equals($coordinate2));
    }

    public function testEqualsIgnoresRelayHintByDefault(): void
    {
        $coordinate1 = $this->createCoordinate();
        $coordinate2 = $this->createCoordinate(self::VALID_RELAY);

        $this->assertTrue($coordinate1->equals($coordinate2));
    }

    public function testEqualsIncludesRelayHintWhenRequested(): void
    {
        $coordinate1 = $this->createCoordinate();
        $coordinate2 = $this->createCoordinate(self::VALID_RELAY);

        $this->assertFalse($coordinate1->equalsIncludingRelayHint($coordinate2));
    }

    public function testEqualsWithMatchingRelayHints(): void
    {
        $coordinate1 = $this->createCoordinate(self::VALID_RELAY);
        $coordinate2 = $this->createCoordinate(self::VALID_RELAY);

        $this->assertTrue($coordinate1->equalsIncludingRelayHint($coordinate2));
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

    public function testTryFromATagReturnsNullForNonStringCoordinate(): void
    {
        $this->assertNull(EventCoordinate::tryFromATag(['a', 12345]));
    }

    public function testRoundTripThroughArray(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $recreated = EventCoordinate::tryFromArray($coordinate->toArray())
            ?? throw new RuntimeException('Failed to recreate coordinate from array');

        $this->assertTrue($coordinate->equalsIncludingRelayHint($recreated));
    }

    public function testMatchesEventReturnsTrueForMatchingEvent(): void
    {
        $coordinate = $this->createCoordinate();
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY);
        $this->assertNotNull($pubkey);

        $event = EventMother::fromRumour(new Rumour(
            $pubkey,
            Timestamp::now(),
            EventKind::fromInt(self::VALID_KIND),
            new TagCollection([Tag::identifier(self::VALID_IDENTIFIER)]),
            EventContent::fromString('test'),
        ));

        $this->assertTrue($coordinate->matchesEvent($event));
    }

    public function testMatchesEventReturnsFalseForWrongKind(): void
    {
        $coordinate = $this->createCoordinate();
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY);
        $this->assertNotNull($pubkey);

        $event = EventMother::fromRumour(new Rumour(
            $pubkey,
            Timestamp::now(),
            EventKind::fromInt(30078),
            new TagCollection([Tag::identifier(self::VALID_IDENTIFIER)]),
            EventContent::fromString('test'),
        ));

        $this->assertFalse($coordinate->matchesEvent($event));
    }

    public function testMatchesEventReturnsFalseForWrongIdentifier(): void
    {
        $coordinate = $this->createCoordinate();
        $pubkey = PublicKey::tryFromHex(self::VALID_PUBKEY);
        $this->assertNotNull($pubkey);

        $event = EventMother::fromRumour(new Rumour(
            $pubkey,
            Timestamp::now(),
            EventKind::fromInt(self::VALID_KIND),
            new TagCollection([Tag::identifier('other-article')]),
            EventContent::fromString('test'),
        ));

        $this->assertFalse($coordinate->matchesEvent($event));
    }
}
