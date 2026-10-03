<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\DecimalIntegerParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalIntegerParserTest extends TestCase
{
    public function testReadsAStringOfDecimalDigits(): void
    {
        $this->assertSame(4096, DecimalIntegerParser::tryParse('4096'));
    }

    public function testReadsZero(): void
    {
        $this->assertSame(0, DecimalIntegerParser::tryParse('0'));
    }

    public function testReadsTheLargestInteger(): void
    {
        $this->assertSame(PHP_INT_MAX, DecimalIntegerParser::tryParse((string) PHP_INT_MAX));
    }

    #[DataProvider('notADecimalInteger')]
    public function testRefusesAnythingButDecimalDigits(string $value): void
    {
        $this->assertNull(DecimalIntegerParser::tryParse($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notADecimalInteger(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => ['-5'];
        yield 'explicit sign' => ['+5'];
        yield 'fraction' => ['1.5'];
        yield 'exponent' => ['1e3'];
        yield 'hexadecimal' => ['0x10'];
        yield 'leading space' => [' 5'];
        yield 'trailing newline' => ["5\n"];
        yield 'words' => ['not-a-number'];
        yield 'a leading zero' => ['007'];
        yield 'zero written twice' => ['00'];
        yield 'beyond the integer range' => ['9223372036854775808'];
    }
}
