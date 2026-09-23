<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\EventCount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventCountTest extends TestCase
{
    public function testAnExactCountKeepsItsNumber(): void
    {
        $this->assertSame(42, EventCount::exact(42)->toInt());
    }

    public function testAnExactCountIsNotApproximate(): void
    {
        $this->assertFalse(EventCount::exact(42)->isApproximate());
    }

    public function testAnApproximateCountSaysSo(): void
    {
        $this->assertTrue(EventCount::approximate(1000)->isApproximate());
    }

    public function testACountCanBeZero(): void
    {
        $this->assertSame(0, EventCount::exact(0)->toInt());
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An event count cannot be negative');

        EventCount::exact(-1);
    }
}
