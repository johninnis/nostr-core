<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\RelayMarker;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use PHPUnit\Framework\TestCase;

final class RelayReferenceTest extends TestCase
{
    private const string VALID_RELAY = 'wss://relay.example.com';

    public function testGetRelayUrlReturnsConstructedValue(): void
    {
        $relay = self::relay();

        $this->assertSame($relay, new RelayReference($relay, RelayMarker::Both)->getRelayUrl());
    }

    public function testGetMarkerReturnsConstructedValue(): void
    {
        $this->assertSame(RelayMarker::Read, new RelayReference(self::relay(), RelayMarker::Read)->getMarker());
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $array = new RelayReference(self::relay(), RelayMarker::Write)->toArray();

        $this->assertSame(['url' => self::VALID_RELAY, 'marker' => 'write'], $array);
    }

    public function testTryFromArrayCreatesValidReference(): void
    {
        $ref = RelayReference::tryFromArray(['url' => self::VALID_RELAY, 'marker' => 'read']);

        $this->assertNotNull($ref);
        $this->assertSame(self::VALID_RELAY, (string) $ref->getRelayUrl());
        $this->assertSame(RelayMarker::Read, $ref->getMarker());
    }

    public function testTryFromArrayWithoutMarkerIsBoth(): void
    {
        $this->assertSame(RelayMarker::Both, RelayReference::tryFromArray(['url' => self::VALID_RELAY])?->getMarker());
    }

    public function testTryFromArrayReturnsNullForInvalidUrl(): void
    {
        $this->assertNull(RelayReference::tryFromArray(['url' => 'not-a-valid-url']));
    }

    public function testTryFromArrayReturnsNullWhenUrlIsMissingOrNonString(): void
    {
        $this->assertNull(RelayReference::tryFromArray([]));
        $this->assertNull(RelayReference::tryFromArray(['url' => 123]));
    }

    public function testRoundTripThroughArray(): void
    {
        $original = new RelayReference(self::relay(), RelayMarker::Write);

        $recreated = RelayReference::tryFromArray($original->toArray());

        $this->assertNotNull($recreated);
        $this->assertSame((string) $original->getRelayUrl(), (string) $recreated->getRelayUrl());
        $this->assertSame($original->getMarker(), $recreated->getMarker());
    }

    private static function relay(): RelayUrl
    {
        return RelayUrl::fromString(self::VALID_RELAY);
    }
}
