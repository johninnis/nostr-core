<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\Core\Domain\ValueObject\CreatedAtWindow;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreatedAtWindowTest extends TestCase
{
    private const int REFERENCE = 1_700_000_000;

    public function testAdmitsTheReferenceInstant(): void
    {
        $this->assertTrue($this->admitsByDefault(self::REFERENCE));
    }

    public function testAdmitsAnHourAheadByDefault(): void
    {
        $this->assertTrue($this->admitsByDefault(self::REFERENCE + 3600));
    }

    public function testRefusesMoreThanAnHourAheadByDefault(): void
    {
        $this->assertFalse($this->admitsByDefault(self::REFERENCE + 3601));
    }

    public function testAdmitsTenYearsBehindByDefault(): void
    {
        $this->assertTrue($this->admitsByDefault(self::REFERENCE - 315_360_000));
    }

    public function testRefusesMoreThanTenYearsBehindByDefault(): void
    {
        $this->assertFalse($this->admitsByDefault(self::REFERENCE - 315_360_001));
    }

    public function testAdmitsUpToTheConfiguredLead(): void
    {
        $window = new CreatedAtWindow(secondsAhead: 60);

        $this->assertSame(
            [true, false],
            [
                $window->admits(Timestamp::fromInt(self::REFERENCE + 60), Timestamp::fromInt(self::REFERENCE)),
                $window->admits(Timestamp::fromInt(self::REFERENCE + 61), Timestamp::fromInt(self::REFERENCE)),
            ],
        );
    }

    public function testAdmitsBackToTheConfiguredAge(): void
    {
        $window = new CreatedAtWindow(secondsBehind: 86_400);

        $this->assertSame(
            [true, false],
            [
                $window->admits(Timestamp::fromInt(self::REFERENCE - 86_400), Timestamp::fromInt(self::REFERENCE)),
                $window->admits(Timestamp::fromInt(self::REFERENCE - 86_401), Timestamp::fromInt(self::REFERENCE)),
            ],
        );
    }

    public function testANegativeLeadIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreatedAtWindow(secondsAhead: -1);
    }

    public function testANegativeAgeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreatedAtWindow(secondsBehind: -1);
    }

    private function admitsByDefault(int $createdAt): bool
    {
        return new CreatedAtWindow()->admits(Timestamp::fromInt($createdAt), Timestamp::fromInt(self::REFERENCE));
    }
}
