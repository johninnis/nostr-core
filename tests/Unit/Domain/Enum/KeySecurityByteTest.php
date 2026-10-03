<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeySecurityByteTest extends TestCase
{
    #[DataProvider('nip49BytesProvider')]
    public function testEachCaseIsBackedByItsNip49Byte(int $byte, KeySecurityByte $case): void
    {
        $this->assertSame($byte, $case->value);
    }

    /**
     * @return iterable<array{int, KeySecurityByte}>
     */
    public static function nip49BytesProvider(): iterable
    {
        yield 'known to have been handled insecurely' => [0x00, KeySecurityByte::KnownInsecure];
        yield 'not known to have been handled insecurely' => [0x01, KeySecurityByte::NotKnownInsecure];
        yield 'not tracked by the client' => [0x02, KeySecurityByte::Untracked];
    }
}
