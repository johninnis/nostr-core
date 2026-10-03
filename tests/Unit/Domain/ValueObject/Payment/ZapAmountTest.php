<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapAmount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testSatsTooLargeToCountInMillisatsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ZapAmount::fromSats(intdiv(PHP_INT_MAX, ZapAmount::MILLISATS_PER_SAT) + 1);
    }

    public function testTheLargestSatsCountableInMillisatsIsAccepted(): void
    {
        $sats = intdiv(PHP_INT_MAX, ZapAmount::MILLISATS_PER_SAT);

        $this->assertSame($sats, ZapAmount::fromSats($sats)->toSats());
    }

    public function testTryFromBolt11MilliBtc(): void
    {
        $amount = ZapAmount::tryFromBolt11('lnbc100m1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000_000_000, $amount->toMillisats());
    }

    public function testTryFromBolt11MicroBtc(): void
    {
        $amount = ZapAmount::tryFromBolt11('lnbc100u1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000_000, $amount->toMillisats());
        $this->assertSame(10_000, $amount->toSats());
    }

    public function testTryFromBolt11NanoBtc(): void
    {
        $amount = ZapAmount::tryFromBolt11('lnbc100n1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000, $amount->toMillisats());
        $this->assertSame(10, $amount->toSats());
    }

    public function testTryFromBolt11PicoBtc(): void
    {
        $amount = ZapAmount::tryFromBolt11('lnbc100p1p...');

        $this->assertNotNull($amount);
        $this->assertSame(10, $amount->toMillisats());
    }

    public function testTryFromBolt11DefaultMultiplier(): void
    {
        $amount = ZapAmount::tryFromBolt11('lnbc11rest');

        $this->assertNotNull($amount);
        $this->assertSame(ZapAmount::MAX_MILLISATS, $amount->toMillisats());
    }

    public function testTryFromBolt11AmountlessInvoiceReturnsNull(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc1rest'));
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc1pvjluezpp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqdq5'));
    }

    #[DataProvider('invoicesWhoseAmountDoesNotEndAtTheSeparator')]
    public function testTryFromBolt11ReadsTheAmountOnlyWhenItEndsAtTheBech32Separator(string $invoice): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11($invoice));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invoicesWhoseAmountDoesNotEndAtTheSeparator(): iterable
    {
        yield 'a 1 inside the human-readable part after the amount' => ['lnbc10u1xyz1qqq'];
        yield 'a 1 after the multiplier before the separator' => ['lnbc1m11qq'];
    }

    public function testTryFromBolt11ParsesUppercaseInvoice(): void
    {
        $amount = ZapAmount::tryFromBolt11('LNBC100M1P...');

        $this->assertNotNull($amount);
        $this->assertSame(10_000_000_000, $amount->toMillisats());
    }

    public function testTryFromBolt11RefusesTheSpecMixedCaseInvoice(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('LNBC2500u1pvjluezpp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqdpquwpc4curk03c9wlrswe78q4eyqc7d8d0xqzpuyk0sg5g70me25alkluzd2x62aysf2pyy8edtjeevuv4p2d5p76r4zkmneet7uvyakky2zr4cusd45tftc9c5fh0nnqpnl2jfll544esqchsrny'));
    }

    public function testTryFromBolt11RefusesAMixedCaseInvoiceWhoseMixIsAfterTheAmount(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc2500u1pvjluezPP5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypq'));
    }

    public function testTryFromBolt11InvalidReturnsNull(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('invalid'));
        $this->assertNull(ZapAmount::tryFromBolt11(''));
    }

    public function testTryFromBolt11AboveMaxReturnsNull(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc21rest'));
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc2100m1p...'));
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc1000001u1p...'));
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc1000000001n1p...'));
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc2000000000001p1p...'));
    }

    public function testTryFromBolt11HugeAmountReturnsNullWithoutOverflow(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc9223372036854775807m1p...'));
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc99999999999999999999999999991p...'));
    }

    public function testTryFromBolt11AtMaxParses(): void
    {
        $milli = ZapAmount::tryFromBolt11('lnbc1000m1p...');

        $this->assertNotNull($milli);
        $this->assertSame(ZapAmount::MAX_MILLISATS, $milli->toMillisats());
    }

    #[DataProvider('specInvoiceAmountProvider')]
    public function testTryFromBolt11ReadsTheAmountOfASpecInvoice(string $bolt11, int $expectedMillisats): void
    {
        $amount = ZapAmount::tryFromBolt11($bolt11);

        $this->assertSame($expectedMillisats, $amount?->toMillisats());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function specInvoiceAmountProvider(): iterable
    {
        yield 'mainnet micro' => ['lnbc2500u1pvjluezsp5zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zygspp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqdq5xysxxatsyp3k7enxv4jsxqzp', 250_000_000];
        yield 'mainnet milli' => ['lnbc20m1pvjluezsp5zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zygspp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqhp58yjmdan79s6qqdhdzgynm4zwqd', 2_000_000_000];
        yield 'testnet milli' => ['lntb20m1pvjluezsp5zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zygshp58yjmdan79s6qqdhdzgynm4zwqd5d7xmw5fk98klysy043l2ahrqspp5qqqsyqcyq5', 2_000_000_000];
        yield 'mainnet pico' => ['lnbc9678785340p1pwmna7lpp5gc3xfm08u9qy06djf8dfflhugl6p7lgza6dsjxq454gxhj9t7a0sd8dgfkx7cmtwd68yetpd5s9xar0wfjn5gpc8qhrsdfq24f5ggrxdaezqsnvda3kkum5wfjkzmfqf', 967_878_534];
        yield 'upper-case mainnet milli' => ['LNBC25M1PVJLUEZPP5QQQSYQCYQ5RQWZQFQQQSYQCYQ5RQWZQFQQQSYQCYQ5RQWZQFQYPQDQ5VDHKVEN9V5SXYETPDEESSP5ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYG3ZYGS9Q5SQQ', 2_500_000_000];
        yield 'signet micro' => ['lntbs10u1pvjluez', 1_000_000];
        yield 'regtest micro' => ['lnbcrt10u1pvjluez', 1_000_000];
        yield 'regtest whole bitcoin' => ['lnbcrt11pvjluez', ZapAmount::MAX_MILLISATS];
    }

    #[DataProvider('unknownCurrencyPrefixProvider')]
    public function testTryFromBolt11ReturnsNullForACurrencyPrefixTheSpecDoesNotDefine(string $bolt11): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11($bolt11));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownCurrencyPrefixProvider(): iterable
    {
        yield 'litecoin' => ['lnltc2500u1pvjluez'];
        yield 'simnet' => ['lnsb2500u1pvjluez'];
        yield 'invented' => ['lnxyz2500u1pvjluez'];
        yield 'mainnet with a trailing letter' => ['lnbcx2500u1pvjluez'];
        yield 'regtest misspelt' => ['lnbcr2500u1pvjluez'];
        yield 'no currency' => ['ln2500u1pvjluez'];
    }

    public function testTryFromBolt11ReturnsNullForTheSpecInvalidSubMillisatoshiPrecisionInvoice(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc2500000001p1pvjluezpp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqdq5xysxxatsyp3k7enxv4jsxqzpusp5zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3z'));
    }

    public function testTryFromBolt11ReturnsNullForTheSpecInvalidMultiplierInvoice(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc2500x1pvjluezpp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqdq5xysxxatsyp3k7enxv4jsxqzpusp5zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg'));
    }

    #[DataProvider('amountsThatAreNotAPositiveIntegerWithoutLeadingZeros')]
    public function testTryFromBolt11RefusesAnAmountThatIsNotAPositiveIntegerWithoutLeadingZeros(string $bolt11): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11($bolt11));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsThatAreNotAPositiveIntegerWithoutLeadingZeros(): iterable
    {
        yield 'a leading zero before a multiplier' => ['lnbc010u1pvjluez'];
        yield 'a leading zero without a multiplier' => ['lnbc011pvjluez'];
        yield 'a zero amount with a multiplier' => ['lnbc0u1pvjluez'];
        yield 'a zero amount without a multiplier' => ['lnbc01pvjluez'];
        yield 'a zero pico amount' => ['lnbc0p1pvjluez'];
        yield 'a run of zeros' => ['lnbc000m1pvjluez'];
    }

    public function testTryFromBolt11RefusesAPicoAmountWhoseLastDigitIsNotZero(): void
    {
        $this->assertNull(ZapAmount::tryFromBolt11('lnbc25p1pvjluez'));
    }

    public function testTryFromBolt11ReadsAPicoAmountWhoseLastDigitIsZero(): void
    {
        $this->assertSame(2, ZapAmount::tryFromBolt11('lnbc20p1pvjluez')?->toMillisats());
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
