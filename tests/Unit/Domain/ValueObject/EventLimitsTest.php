<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\Core\Domain\ValueObject\CreatedAtWindow;
use Innis\Nostr\Core\Domain\ValueObject\EventLimits;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventLimitsTest extends TestCase
{
    public function testAdmitsContentUpTo65536CharactersByDefault(): void
    {
        $this->assertSame([true, false], [new EventLimits()->admitsContentLength(65_536), new EventLimits()->admitsContentLength(65_537)]);
    }

    public function testAdmitsUpTo5000TagsByDefault(): void
    {
        $this->assertSame([true, false], [new EventLimits()->admitsTagCount(5000), new EventLimits()->admitsTagCount(5001)]);
    }

    public function testAdmitsContentUpToTheConfiguredLength(): void
    {
        $limits = new EventLimits(maxContentLength: 200_000);

        $this->assertSame([true, false], [$limits->admitsContentLength(200_000), $limits->admitsContentLength(200_001)]);
    }

    public function testAdmitsTagsUpToTheConfiguredCount(): void
    {
        $limits = new EventLimits(maxTagCount: 10);

        $this->assertSame([true, false], [$limits->admitsTagCount(10), $limits->admitsTagCount(11)]);
    }

    public function testAdmitsCreatedAtThroughTheConfiguredWindow(): void
    {
        $limits = new EventLimits(createdAtWindow: new CreatedAtWindow(secondsAhead: 0));
        $reference = Timestamp::fromInt(1_700_000_000);

        $this->assertSame(
            [true, false],
            [
                $limits->admitsCreatedAt(Timestamp::fromInt(1_700_000_000), $reference),
                $limits->admitsCreatedAt(Timestamp::fromInt(1_700_000_001), $reference),
            ],
        );
    }

    public function testAZeroContentLengthIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventLimits(maxContentLength: 0);
    }

    public function testAZeroTagCountIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventLimits(maxTagCount: 0);
    }
}
