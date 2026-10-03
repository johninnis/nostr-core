<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\HexCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class HexCodecTest extends TestCase
{
    #[DataProvider('conversionsTheLanguageProvides')]
    public function testDoesNotAliasAConversionSodiumProvides(string $method): void
    {
        $this->assertFalse(new ReflectionClass(HexCodec::class)->hasMethod($method));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function conversionsTheLanguageProvides(): iterable
    {
        yield 'encode' => ['encode'];
        yield 'decode' => ['decode'];
    }

    public function testTheCanonicalHexIsReturnedAndAnythingElseIsRefused(): void
    {
        $this->assertSame('00ff', HexCodec::tryCanonical('00ff', 2));
        $this->assertNull(HexCodec::tryCanonical('00ff', 3));
        $this->assertNull(HexCodec::tryCanonical('00fg', 2));
    }

    public function testATrailingNewlineIsRefused(): void
    {
        $this->assertNull(HexCodec::tryCanonical("00ff\n", 2));
    }
}
