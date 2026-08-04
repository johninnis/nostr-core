<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nip19Tlv;
use PHPUnit\Framework\TestCase;

final class Nip19TlvTest extends TestCase
{
    public function testEncodesRecordsAtTheUint8Bounds(): void
    {
        $tlv = Nip19Tlv::tryFromRecords([
            ['type' => 255, 'value' => str_repeat('v', 255)],
        ]);

        $this->assertNotNull($tlv);
        $this->assertSame(str_repeat('v', 255), $tlv->first(255));
    }

    // Deliberate: T and L are both uint8; pack('C') wraps rather than rejecting, and a wrapped type would index in memory under a value the bytes do not carry — see ADR-0059
    public function testRejectsRecordTypeBeyondTheUint8Range(): void
    {
        $this->assertNull(Nip19Tlv::tryFromRecords([['type' => 256, 'value' => 'x']]));
        $this->assertNull(Nip19Tlv::tryFromRecords([['type' => -1, 'value' => 'x']]));
    }

    public function testRejectsRecordValueBeyondTheUint8Range(): void
    {
        $this->assertNull(Nip19Tlv::tryFromRecords([['type' => 0, 'value' => str_repeat('v', 256)]]));
    }

    public function testDecodeRejectsATrailingTypeByteWithoutALength(): void
    {
        $this->assertNull(Nip19Tlv::tryFromBytes(pack('CC', 0, 1).'x'.chr(1)));
    }

    public function testDecodeRejectsAValueShorterThanItsDeclaredLength(): void
    {
        $this->assertNull(Nip19Tlv::tryFromBytes(pack('CC', 0, 50).'abc'));
    }

    public function testRoundTripsRecordsThroughBytes(): void
    {
        $tlv = Nip19Tlv::tryFromRecords([
            ['type' => Nip19Tlv::TYPE_SPECIAL, 'value' => 'id'],
            ['type' => Nip19Tlv::TYPE_RELAY, 'value' => 'wss://a.example.com'],
            ['type' => Nip19Tlv::TYPE_RELAY, 'value' => 'wss://b.example.com'],
        ]);
        $this->assertNotNull($tlv);

        $decoded = Nip19Tlv::tryFromBytes($tlv->toBytes());

        $this->assertNotNull($decoded);
        $this->assertSame('id', $decoded->first(Nip19Tlv::TYPE_SPECIAL));
        $this->assertSame(['wss://a.example.com', 'wss://b.example.com'], $decoded->all(Nip19Tlv::TYPE_RELAY));
        $this->assertNull($decoded->first(Nip19Tlv::TYPE_AUTHOR));
        $this->assertSame([], $decoded->all(Nip19Tlv::TYPE_AUTHOR));
    }
}
