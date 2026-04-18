<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeySecurityByteTest extends TestCase
{
    #[DataProvider('knownCasesProvider')]
    public function testFromByteRoundTrips(int $byte, KeySecurityByte $expected): void
    {
        $this->assertSame($expected, KeySecurityByte::fromByte($byte));
    }

    public function testFromByteThrowsForUnknownValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KeySecurityByte::fromByte(0x10);
    }

    /**
     * @return iterable<array{int, KeySecurityByte}>
     */
    public static function knownCasesProvider(): iterable
    {
        yield 'client-side only' => [0x00, KeySecurityByte::ClientSideOnly];
        yield 'usable untrusted' => [0x01, KeySecurityByte::UsableUntrusted];
        yield 'unknown' => [0x02, KeySecurityByte::Unknown];
    }
}
