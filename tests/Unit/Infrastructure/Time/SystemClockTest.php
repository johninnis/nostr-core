<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Time;

use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Time\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testNowReturnsTheCurrentInstant(): void
    {
        $before = Timestamp::now()->toInt();
        $now = new SystemClock()->now();
        $after = Timestamp::now()->toInt();

        $this->assertGreaterThanOrEqual($before, $now->toInt());
        $this->assertLessThanOrEqual($after, $now->toInt());
    }
}
