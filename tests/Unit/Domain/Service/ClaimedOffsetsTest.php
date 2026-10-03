<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\ClaimedOffsets;
use PHPUnit\Framework\TestCase;

final class ClaimedOffsetsTest extends TestCase
{
    public function testClaimsAFreeSpan(): void
    {
        $this->assertTrue(new ClaimedOffsets()->claim(0, 5));
    }

    public function testRefusesASpanOverlappingAClaimedOne(): void
    {
        $claims = new ClaimedOffsets();
        $claims->claim(10, 5);

        $this->assertFalse($claims->claim(14, 5));
    }

    public function testRefusesASpanInsideAClaimedOne(): void
    {
        $claims = new ClaimedOffsets();
        $claims->claim(10, 10);

        $this->assertFalse($claims->claim(12, 3));
    }

    public function testClaimsASpanStartingWhereAClaimedOneEnds(): void
    {
        $claims = new ClaimedOffsets();
        $claims->claim(10, 5);

        $this->assertTrue($claims->claim(15, 5));
    }

    public function testARefusedSpanClaimsNothing(): void
    {
        $claims = new ClaimedOffsets();
        $claims->claim(10, 5);
        $claims->claim(12, 10);

        $this->assertTrue($claims->claim(15, 7));
    }

    public function testWorkIsTheTotalLengthOfTheSpansChecked(): void
    {
        $claims = new ClaimedOffsets();

        for ($span = 0; $span < 16000; ++$span) {
            $claims->claim($span * 9, 9);
        }

        $this->assertSame(16000 * 9, $claims->offsetsExamined());
    }

    public function testARefusedSpanCostsNoMoreThanItsLength(): void
    {
        $claims = new ClaimedOffsets();
        for ($span = 0; $span < 1000; ++$span) {
            $claims->claim($span * 9, 9);
        }
        $before = $claims->offsetsExamined();

        $claims->claim(4500, 9);

        $this->assertLessThanOrEqual(9, $claims->offsetsExamined() - $before);
    }
}
