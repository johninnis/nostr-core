<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject;

use DateTimeImmutable;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    public function testCanCreateFromInt(): void
    {
        $timestamp = Timestamp::fromInt(1234567890);

        $this->assertSame(1234567890, $timestamp->toInt());
        $this->assertSame('1234567890', (string) $timestamp);
    }

    public function testThrowsExceptionForNegativeTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp cannot be negative');

        Timestamp::fromInt(-1);
    }

    public function testCanCreateNow(): void
    {
        $timestamp = Timestamp::now();
        $currentTime = time();

        $this->assertGreaterThanOrEqual($currentTime - 1, $timestamp->toInt());
        $this->assertLessThanOrEqual($currentTime + 1, $timestamp->toInt());
    }

    public function testCanCreateFromDateTime(): void
    {
        $dateTime = new DateTimeImmutable('2023-01-01 12:00:00 UTC');
        $timestamp = Timestamp::fromDateTime($dateTime);

        $this->assertSame(1672574400, $timestamp->toInt());
    }

    public function testCanConvertToDateTime(): void
    {
        $timestamp = Timestamp::fromInt(1672574400);
        $dateTime = $timestamp->toDateTime();

        $this->assertInstanceOf(DateTimeImmutable::class, $dateTime);
        $this->assertSame('2023-01-01 12:00:00', $dateTime->format('Y-m-d H:i:s'));
    }

    public function testIsReasonableWorksCorrectly(): void
    {
        $now = time();
        $reasonableTimestamp = Timestamp::fromInt($now);
        $futureTimestamp = Timestamp::fromInt($now + 7200); // 2 hours in future
        $veryOldTimestamp = Timestamp::fromInt($now - (11 * 365 * 24 * 3600)); // 11 years ago

        $this->assertTrue($reasonableTimestamp->isReasonable());
        $this->assertFalse($futureTimestamp->isReasonable());
        $this->assertFalse($veryOldTimestamp->isReasonable());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $timestamp1 = Timestamp::fromInt(1234567890);
        $timestamp2 = Timestamp::fromInt(1234567890);
        $timestamp3 = Timestamp::fromInt(1234567891);

        $this->assertTrue($timestamp1->equals($timestamp2));
        $this->assertFalse($timestamp1->equals($timestamp3));
    }

    public function testIsAfterWorksCorrectly(): void
    {
        $earlier = Timestamp::fromInt(1234567890);
        $later = Timestamp::fromInt(1234567891);

        $this->assertTrue($later->isAfter($earlier));
        $this->assertFalse($earlier->isAfter($later));
        $this->assertFalse($earlier->isAfter($earlier));
    }

    public function testIsBeforeWorksCorrectly(): void
    {
        $earlier = Timestamp::fromInt(1234567890);
        $later = Timestamp::fromInt(1234567891);

        $this->assertTrue($earlier->isBefore($later));
        $this->assertFalse($later->isBefore($earlier));
        $this->assertFalse($earlier->isBefore($earlier));
    }
}
