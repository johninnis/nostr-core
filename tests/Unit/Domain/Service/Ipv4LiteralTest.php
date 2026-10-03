<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\Ipv4Literal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Ipv4LiteralTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function dottedDecimals(): iterable
    {
        yield 'zeros' => ['0.0.0.0'];
        yield 'loopback' => ['127.0.0.1'];
        yield 'maximum' => ['255.255.255.255'];
        yield 'two-digit octets' => ['10.20.30.40'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDottedDecimals(): iterable
    {
        yield 'octet above 255' => ['256.0.0.1'];
        yield 'leading zero' => ['127.0.0.01'];
        yield 'three octets' => ['127.0.1'];
        yield 'five octets' => ['1.2.3.4.5'];
        yield 'trailing dot' => ['1.2.3.4.'];
        yield 'hex octet' => ['0x7f.0.0.1'];
        yield 'single number' => ['2130706433'];
        yield 'trailing newline' => ["1.2.3.4\n"];
        yield 'empty' => [''];
    }

    #[DataProvider('dottedDecimals')]
    public function testMatchesACanonicalDottedDecimalAddress(string $text): void
    {
        $this->assertTrue(Ipv4Literal::matches($text));
    }

    #[DataProvider('notDottedDecimals')]
    public function testRefusesAnythingElse(string $text): void
    {
        $this->assertFalse(Ipv4Literal::matches($text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostsEndingInANumber(): iterable
    {
        yield 'dotted decimal' => ['127.0.0.1'];
        yield 'shortened address' => ['127.1'];
        yield 'single number' => ['2130706433'];
        yield 'decimal last label' => ['example.123'];
        yield 'hexadecimal last label' => ['example.0x1f'];
        yield 'upper-case hexadecimal prefix' => ['example.0X1F'];
        yield 'bare hexadecimal prefix' => ['example.0x'];
        yield 'one trailing dot' => ['example.123.'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostsNotEndingInANumber(): iterable
    {
        yield 'name' => ['example.com'];
        yield 'digits then letters' => ['example.123a'];
        yield 'number not last' => ['123.example'];
        yield 'two trailing dots' => ['example.123..'];
        yield 'hexadecimal digit without prefix' => ['example.1f'];
        yield 'empty' => [''];
    }

    #[DataProvider('hostsEndingInANumber')]
    public function testRecognisesAHostTheWhatwgParserReadsAsIpv4(string $host): void
    {
        $this->assertTrue(Ipv4Literal::endsInNumber($host));
    }

    #[DataProvider('hostsNotEndingInANumber')]
    public function testRecognisesAHostTheWhatwgParserReadsAsADomain(string $host): void
    {
        $this->assertFalse(Ipv4Literal::endsInNumber($host));
    }
}
