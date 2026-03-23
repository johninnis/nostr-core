<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    public function testCanCreateValidEventId(): void
    {
        $hex = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $eventId = EventId::fromHex($hex);

        $this->assertNotNull($eventId);
        $this->assertSame($hex, $eventId->toHex());
        $this->assertSame($hex, (string) $eventId);
    }

    public function testReturnsNullForInvalidHexFormat(): void
    {
        $this->assertNull(EventId::fromHex('invalid-hex'));
    }

    public function testReturnsNullForWrongLength(): void
    {
        $this->assertNull(EventId::fromHex('123456'));
    }

    public function testEqualsWorksCorrectly(): void
    {
        $hex = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $eventId1 = EventId::fromHex($hex) ?? throw new \RuntimeException('Invalid test event ID');
        $eventId2 = EventId::fromHex($hex);
        $this->assertNotNull($eventId2);
        $eventId3 = EventId::fromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210');
        $this->assertNotNull($eventId3);

        $this->assertTrue($eventId1->equals($eventId2));
        $this->assertFalse($eventId1->equals($eventId3));
    }

    public function testCanConvertToBech32(): void
    {
        $eventId = EventId::fromHex('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef') ?? throw new \RuntimeException('Invalid test event ID');
        $bech32 = $eventId->toBech32();

        $this->assertStringStartsWith('note1', $bech32);
    }

    public function testCanCreateFromBech32(): void
    {
        $eventId = EventId::fromHex('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef') ?? throw new \RuntimeException('Invalid test event ID');
        $bech32 = $eventId->toBech32();
        $recreated = EventId::fromBech32($bech32);

        $this->assertNotNull($recreated);
        $this->assertTrue($eventId->equals($recreated));
    }

    public function testFromBech32ReturnsNullForInvalidPrefix(): void
    {
        $this->assertNull(EventId::fromBech32('npub1abc'));
    }

    public function testFromBech32ReturnsNullForInvalidData(): void
    {
        $this->assertNull(EventId::fromBech32('note1invaliddata'));
    }
}
