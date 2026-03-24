<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventKindTest extends TestCase
{
    public function testCanCreateFromInt(): void
    {
        $kind = EventKind::fromInt(1);

        $this->assertSame(1, $kind->toInt());
        $this->assertSame('1', (string) $kind);
    }

    public function testThrowsExceptionForNegativeKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event kind must be between 0 and 65535');

        EventKind::fromInt(-1);
    }

    public function testThrowsExceptionForTooLargeKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event kind must be between 0 and 65535');

        EventKind::fromInt(65536);
    }

    public function testStaticFactoryMethods(): void
    {
        $this->assertSame(0, EventKind::metadata()->toInt());
        $this->assertSame(1, EventKind::textNote()->toInt());
        $this->assertSame(3, EventKind::followList()->toInt());
        $this->assertSame(4, EventKind::encryptedDirectMessage()->toInt());
        $this->assertSame(5, EventKind::eventDeletion()->toInt());
    }

    public function testIsRegular(): void
    {
        $regularKind = EventKind::fromInt(1);
        $replaceableKind = EventKind::fromInt(10000);

        $this->assertTrue($regularKind->isRegular());
        $this->assertFalse($replaceableKind->isRegular());
    }

    public function testIsReplaceable(): void
    {
        $regularKind = EventKind::fromInt(1);
        $replaceableKind = EventKind::fromInt(10000);
        $upperBoundKind = EventKind::fromInt(19999);
        $beyondBoundKind = EventKind::fromInt(20000);

        $this->assertFalse($regularKind->isReplaceable());
        $this->assertTrue($replaceableKind->isReplaceable());
        $this->assertTrue($upperBoundKind->isReplaceable());
        $this->assertFalse($beyondBoundKind->isReplaceable());
    }

    public function testIsEphemeral(): void
    {
        $regularKind = EventKind::fromInt(1);
        $ephemeralKind = EventKind::fromInt(20000);
        $upperBoundKind = EventKind::fromInt(29999);
        $beyondBoundKind = EventKind::fromInt(30000);

        $this->assertFalse($regularKind->isEphemeral());
        $this->assertTrue($ephemeralKind->isEphemeral());
        $this->assertTrue($upperBoundKind->isEphemeral());
        $this->assertFalse($beyondBoundKind->isEphemeral());
    }

    public function testIsParameterisedReplaceable(): void
    {
        $regularKind = EventKind::fromInt(1);
        $parameterisedKind = EventKind::fromInt(30000);
        $upperBoundKind = EventKind::fromInt(39999);
        $beyondBoundKind = EventKind::fromInt(40000);

        $this->assertFalse($regularKind->isParameterisedReplaceable());
        $this->assertTrue($parameterisedKind->isParameterisedReplaceable());
        $this->assertTrue($upperBoundKind->isParameterisedReplaceable());
        $this->assertFalse($beyondBoundKind->isParameterisedReplaceable());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $kind1 = EventKind::fromInt(1);
        $kind2 = EventKind::fromInt(1);
        $kind3 = EventKind::fromInt(2);

        $this->assertTrue($kind1->equals($kind2));
        $this->assertFalse($kind1->equals($kind3));
    }

    public function testAdditionalStaticFactoryMethods(): void
    {
        $this->assertSame(6, EventKind::repost()->toInt());
        $this->assertSame(7, EventKind::reaction()->toInt());
        $this->assertSame(1111, EventKind::comment()->toInt());
        $this->assertSame(30023, EventKind::longformContent()->toInt());
        $this->assertSame(30311, EventKind::liveEvent()->toInt());
        $this->assertSame(10002, EventKind::relayList()->toInt());
        $this->assertSame(10000, EventKind::muteList()->toInt());
        $this->assertSame(21, EventKind::video()->toInt());
        $this->assertSame(22, EventKind::shortFormVideo()->toInt());
        $this->assertSame(22242, EventKind::clientAuth()->toInt());
        $this->assertSame(24133, EventKind::nostrConnect()->toInt());
        $this->assertSame(9735, EventKind::zapReceipt()->toInt());
        $this->assertSame(9321, EventKind::nutzap()->toInt());
    }

    public function testToStringReturnsKindAsString(): void
    {
        $this->assertSame('42', (string) EventKind::fromInt(42));
    }

    public function testBoundaryKindValues(): void
    {
        $zero = EventKind::fromInt(0);
        $max = EventKind::fromInt(65535);

        $this->assertSame(0, $zero->toInt());
        $this->assertSame(65535, $max->toInt());
    }
}
