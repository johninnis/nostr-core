<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\SerialisationException;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use PHPUnit\Framework\TestCase;

final class JsonWireFormatTest extends TestCase
{
    public function testEncodeReturnsJsonForAValidValue(): void
    {
        $this->assertSame('{"a":1}', JsonWireFormat::encode(['a' => 1], JsonWireFormat::MESSAGE));
    }

    public function testEncodeThrowsSerialisationExceptionForInvalidUtf8(): void
    {
        $this->expectException(SerialisationException::class);
        $this->expectExceptionMessage('Failed to serialise value to JSON');

        JsonWireFormat::encode("\xb1\x31", JsonWireFormat::EVENT);
    }

    public function testDecodeArrayReturnsNullForJsonNestedBeyondTheDepthLimit(): void
    {
        $depthBomb = str_repeat('[', 600).str_repeat(']', 600);

        $this->assertNull(JsonWireFormat::decodeArray($depthBomb));
    }

    public function testDecodeArrayDecodesJsonWithinTheDepthLimit(): void
    {
        $this->assertSame(['a' => 1], JsonWireFormat::decodeArray('{"a":1}'));
    }

    public function testDecodeArrayHonoursAnExplicitlyTightenedDepthLimit(): void
    {
        $tenDeep = str_repeat('[', 10).str_repeat(']', 10);

        $this->assertNull(JsonWireFormat::decodeArray($tenDeep, 5));
    }
}
