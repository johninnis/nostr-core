<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\RelayMarker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RelayMarkerTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, RelayMarker}>
     */
    public static function tagValues(): iterable
    {
        yield 'read' => ['read', RelayMarker::Read];
        yield 'write' => ['write', RelayMarker::Write];
        yield 'absent' => [null, RelayMarker::Both];
        yield 'empty' => ['', RelayMarker::Both];
        yield 'unrecognised' => ['readwrite', RelayMarker::Both];
        yield 'wrong case' => ['READ', RelayMarker::Both];
    }

    #[DataProvider('tagValues')]
    public function testFromTagValueReadsANip65Marker(?string $value, RelayMarker $expected): void
    {
        $this->assertSame($expected, RelayMarker::fromTagValue($value));
    }
}
