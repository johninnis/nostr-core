<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ZapAmountTest extends TestCase
{
    public function testFromMillisats(): void
    {
        $amount = ZapAmount::fromMillisats(5000);

        $this->assertSame(5000, $amount->toMillisats());
        $this->assertSame(5, $amount->toSats());
    }

    public function testFromSats(): void
    {
        $amount = ZapAmount::fromSats(10);

        $this->assertSame(10_000, $amount->toMillisats());
        $this->assertSame(10, $amount->toSats());
    }

    public function testToSatsTruncates(): void
    {
        $amount = ZapAmount::fromMillisats(1500);

        $this->assertSame(1, $amount->toSats());
    }

    public function testZeroIsValid(): void
    {
        $amount = ZapAmount::fromMillisats(0);

        $this->assertSame(0, $amount->toMillisats());
        $this->assertSame(0, $amount->toSats());
    }

    public function testNegativeMillisatsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        ZapAmount::fromMillisats(-1);
    }

    public function testNegativeSatsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        ZapAmount::fromSats(-1);
    }

    public function testFromBolt11MilliBtc(): void
    {
        $amount = ZapAmount::fromBolt11('lnbc100m1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000_000_000, $amount->toMillisats());
    }

    public function testFromBolt11MicroBtc(): void
    {
        $amount = ZapAmount::fromBolt11('lnbc100u1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000_000, $amount->toMillisats());
        $this->assertSame(10_000, $amount->toSats());
    }

    public function testFromBolt11NanoBtc(): void
    {
        $amount = ZapAmount::fromBolt11('lnbc100n1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000, $amount->toMillisats());
        $this->assertSame(10, $amount->toSats());
    }

    public function testFromBolt11PicoBtc(): void
    {
        $amount = ZapAmount::fromBolt11('lnbc100p1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10, $amount->toMillisats());
    }

    public function testFromBolt11DefaultMultiplier(): void
    {
        $amount = ZapAmount::fromBolt11('lnbc11rest');

        $this->assertNotNull($amount);
        $this->assertSame(ZapAmount::MAX_MILLISATS, $amount->toMillisats());
    }

    public function testFromBolt11AmountlessInvoiceReturnsNull(): void
    {
        $this->assertNull(ZapAmount::fromBolt11('lnbc1rest'));
        $this->assertNull(ZapAmount::fromBolt11('lnbc1pvjluezpp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqdq5'));
    }

    public function testFromBolt11ParsesUppercaseInvoice(): void
    {
        $amount = ZapAmount::fromBolt11('LNBC100M1P...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000_000_000, $amount->toMillisats());
    }

    public function testFromBolt11InvalidReturnsNull(): void
    {
        $this->assertNull(ZapAmount::fromBolt11('invalid'));
        $this->assertNull(ZapAmount::fromBolt11(''));
    }

    public function testFromBolt11AboveMaxReturnsNull(): void
    {
        $this->assertNull(ZapAmount::fromBolt11('lnbc21rest'));
        $this->assertNull(ZapAmount::fromBolt11('lnbc2100m1p...'));
        $this->assertNull(ZapAmount::fromBolt11('lnbc1000001u1p...'));
        $this->assertNull(ZapAmount::fromBolt11('lnbc1000000001n1p...'));
        $this->assertNull(ZapAmount::fromBolt11('lnbc2000000000001p1p...'));
    }

    public function testFromBolt11HugeAmountReturnsNullWithoutOverflow(): void
    {
        $this->assertNull(ZapAmount::fromBolt11('lnbc9223372036854775807m1p...'));
        $this->assertNull(ZapAmount::fromBolt11('lnbc99999999999999999999999999991p...'));
    }

    public function testFromBolt11AtMaxParses(): void
    {
        $milli = ZapAmount::fromBolt11('lnbc1000m1p...');

        $this->assertNotNull($milli);
        $this->assertSame(ZapAmount::MAX_MILLISATS, $milli->toMillisats());
    }

    public function testEquals(): void
    {
        $a = ZapAmount::fromMillisats(1000);
        $b = ZapAmount::fromSats(1);
        $c = ZapAmount::fromMillisats(2000);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
