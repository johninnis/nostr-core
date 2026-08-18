<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\HexCodec;
use PHPUnit\Framework\TestCase;

final class HexCodecTest extends TestCase
{
    public function testEncodeProducesLowercaseHex(): void
    {
        $this->assertSame('00ff10', HexCodec::encode("\x00\xff\x10"));
    }

    public function testDecodeReversesEncode(): void
    {
        $bytes = "\x00\xff\x10\x2a";

        $this->assertSame($bytes, HexCodec::decode(HexCodec::encode($bytes)));
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
