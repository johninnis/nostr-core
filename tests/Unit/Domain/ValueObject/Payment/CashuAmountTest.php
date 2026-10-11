<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\ValueObject\Payment\CashuAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CashuAmountTest extends TestCase
{
    public function testItCarriesItsAmount(): void
    {
        $this->assertSame(21, CashuAmount::from(21, 'usd')->getAmount());
    }

    public function testItCarriesItsUnit(): void
    {
        $this->assertSame('usd', CashuAmount::from(21, 'usd')->getUnit());
    }

    public function testANegativeAmountIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CashuAmount::from(-1, 'sat');
    }

    public function testAmountsInDifferentUnitsAreNotEqual(): void
    {
        $this->assertFalse(CashuAmount::from(21, 'usd')->equals(CashuAmount::from(21, 'eur')));
    }

    public function testTheSameAmountInTheSameUnitIsEqual(): void
    {
        $this->assertTrue(CashuAmount::from(21, 'usd')->equals(CashuAmount::from(21, 'usd')));
    }
}
