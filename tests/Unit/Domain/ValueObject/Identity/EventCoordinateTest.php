<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventCoordinateTest extends TestCase
{
    private const VALID_PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const VALID_KIND = 30023;
    private const VALID_IDENTIFIER = 'my-article';
    private const VALID_RELAY = 'wss://relay.example.com';

    private function createCoordinate(?string $relayHint = null): EventCoordinate
    {
        return EventCoordinate::fromParts(self::VALID_KIND, self::VALID_PUBKEY, self::VALID_IDENTIFIER, $relayHint)
            ?? throw new RuntimeException('Failed to create test coordinate');
    }

    public function testFromPartsCreatesValidCoordinate(): void
    {
        $coordinate = $this->createCoordinate();

        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
        $this->assertSame(self::VALID_PUBKEY, $coordinate->getPubkey()->toHex());
        $this->assertSame(self::VALID_IDENTIFIER, $coordinate->getIdentifier());
        $this->assertNull($coordinate->getRelayHint());
    }

    public function testFromPartsWithRelayHint(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $this->assertNotNull($coordinate->getRelayHint());
        $this->assertSame(self::VALID_RELAY, (string) $coordinate->getRelayHint());
    }

    public function testFromPartsReturnsNullForNonParameterisedReplaceableKind(): void
    {
        $this->assertNull(EventCoordinate::fromParts(1, self::VALID_PUBKEY, self::VALID_IDENTIFIER));
    }

    public function testFromPartsReturnsNullForInvalidPubkey(): void
    {
        $this->assertNull(EventCoordinate::fromParts(self::VALID_KIND, 'invalid', self::VALID_IDENTIFIER));
    }

    public function testFromPartsReturnsNullForEmptyIdentifier(): void
    {
        $this->assertNull(EventCoordinate::fromParts(self::VALID_KIND, self::VALID_PUBKEY, ''));
    }

    public function testFromStringParsesValidCoordinate(): void
    {
        $coordinateString = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $coordinate = EventCoordinate::fromString($coordinateString)
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
        $this->assertSame(self::VALID_PUBKEY, $coordinate->getPubkey()->toHex());
        $this->assertSame(self::VALID_IDENTIFIER, $coordinate->getIdentifier());
    }

    public function testFromStringHandlesIdentifierWithColons(): void
    {
        $coordinateString = self::VALID_KIND.':'.self::VALID_PUBKEY.':part1:part2:part3';
        $coordinate = EventCoordinate::fromString($coordinateString)
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertSame('part1:part2:part3', $coordinate->getIdentifier());
    }

    public function testFromStringReturnsNullForFewerThanThreeParts(): void
    {
        $this->assertNull(EventCoordinate::fromString('30023:'.self::VALID_PUBKEY));
    }

    public function testFromStringWithRelayHint(): void
    {
        $coordinateString = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $coordinate = EventCoordinate::fromString($coordinateString, self::VALID_RELAY)
            ?? throw new RuntimeException('Failed to parse coordinate string');

        $this->assertNotNull($coordinate->getRelayHint());
    }

    public function testFromATagParsesValidTag(): void
    {
        $tag = ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER];
        $coordinate = EventCoordinate::fromATag($tag)
            ?? throw new RuntimeException('Failed to parse a-tag');

        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
    }

    public function testFromATagWithRelayHint(): void
    {
        $tag = ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, self::VALID_RELAY];
        $coordinate = EventCoordinate::fromATag($tag)
            ?? throw new RuntimeException('Failed to parse a-tag');

        $this->assertNotNull($coordinate->getRelayHint());
    }

    public function testFromATagReturnsNullForNonATag(): void
    {
        $this->assertNull(EventCoordinate::fromATag(['p', self::VALID_PUBKEY]));
    }

    public function testFromATagReturnsNullForMissingValue(): void
    {
        $this->assertNull(EventCoordinate::fromATag(['a']));
    }

    public function testFromATagIgnoresEmptyRelayHint(): void
    {
        $tag = ['a', self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER, ''];
        $coordinate = EventCoordinate::fromATag($tag)
            ?? throw new RuntimeException('Failed to parse a-tag');

        $this->assertNull($coordinate->getRelayHint());
    }

    public function testToStringReturnsCoordinateFormat(): void
    {
        $coordinate = $this->createCoordinate();

        $expected = self::VALID_KIND.':'.self::VALID_PUBKEY.':'.self::VALID_IDENTIFIER;
        $this->assertSame($expected, $coordinate->toString());
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
        $relayUrl = RelayUrl::fromString(self::VALID_RELAY);

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
        $coordinate1 = EventCoordinate::fromParts(self::VALID_KIND, self::VALID_PUBKEY, 'article-one')
            ?? throw new RuntimeException('Failed to create test coordinate');
        $coordinate2 = EventCoordinate::fromParts(self::VALID_KIND, self::VALID_PUBKEY, 'article-two')
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

        $this->assertFalse($coordinate1->equals($coordinate2, true));
    }

    public function testEqualsWithMatchingRelayHints(): void
    {
        $coordinate1 = $this->createCoordinate(self::VALID_RELAY);
        $coordinate2 = $this->createCoordinate(self::VALID_RELAY);

        $this->assertTrue($coordinate1->equals($coordinate2, true));
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

    public function testFromArrayCreatesValidCoordinate(): void
    {
        $data = [
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
        ];

        $coordinate = EventCoordinate::fromArray($data);

        $this->assertNotNull($coordinate);
        $this->assertSame(self::VALID_KIND, $coordinate->getKind()->toInt());
    }

    public function testFromArrayWithRelayHint(): void
    {
        $data = [
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
            'relay_hint' => self::VALID_RELAY,
        ];

        $coordinate = EventCoordinate::fromArray($data);

        $this->assertNotNull($coordinate);
        $this->assertNotNull($coordinate->getRelayHint());
    }

    public function testFromArrayReturnsNullForMissingKind(): void
    {
        $this->assertNull(EventCoordinate::fromArray([
            'pubkey' => self::VALID_PUBKEY,
            'identifier' => self::VALID_IDENTIFIER,
        ]));
    }

    public function testFromArrayReturnsNullForMissingPubkey(): void
    {
        $this->assertNull(EventCoordinate::fromArray([
            'kind' => self::VALID_KIND,
            'identifier' => self::VALID_IDENTIFIER,
        ]));
    }

    public function testFromArrayReturnsNullForMissingIdentifier(): void
    {
        $this->assertNull(EventCoordinate::fromArray([
            'kind' => self::VALID_KIND,
            'pubkey' => self::VALID_PUBKEY,
        ]));
    }

    public function testRoundTripThroughArray(): void
    {
        $coordinate = $this->createCoordinate(self::VALID_RELAY);

        $recreated = EventCoordinate::fromArray($coordinate->toArray())
            ?? throw new RuntimeException('Failed to recreate coordinate from array');

        $this->assertTrue($coordinate->equals($recreated, true));
    }
}
