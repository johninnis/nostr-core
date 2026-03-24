<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayReferenceTest extends TestCase
{
    private const VALID_RELAY = 'wss://relay.example.com';

    public function testConstructorWithRelayUrlOnly(): void
    {
        $relay = RelayUrl::fromString(self::VALID_RELAY) ?? throw new RuntimeException('Invalid test relay');
        $ref = new RelayReference($relay);

        $this->assertSame($relay, $ref->getRelayUrl());
        $this->assertNull($ref->getMode());
    }

    public function testConstructorWithMode(): void
    {
        $relay = RelayUrl::fromString(self::VALID_RELAY) ?? throw new RuntimeException('Invalid test relay');
        $ref = new RelayReference($relay, 'read');

        $this->assertSame($relay, $ref->getRelayUrl());
        $this->assertSame('read', $ref->getMode());
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $relay = RelayUrl::fromString(self::VALID_RELAY) ?? throw new RuntimeException('Invalid test relay');
        $ref = new RelayReference($relay, 'write');

        $array = $ref->toArray();
        $this->assertSame(self::VALID_RELAY, $array['url']);
        $this->assertSame('write', $array['mode']);
    }

    public function testToArrayWithNullMode(): void
    {
        $relay = RelayUrl::fromString(self::VALID_RELAY) ?? throw new RuntimeException('Invalid test relay');
        $ref = new RelayReference($relay);

        $array = $ref->toArray();
        $this->assertSame(self::VALID_RELAY, $array['url']);
        $this->assertNull($array['mode']);
    }

    public function testFromArrayCreatesValidReference(): void
    {
        $data = [
            'url' => self::VALID_RELAY,
            'mode' => 'read',
        ];

        $ref = RelayReference::fromArray($data);

        $this->assertSame(self::VALID_RELAY, (string) $ref->getRelayUrl());
        $this->assertSame('read', $ref->getMode());
    }

    public function testFromArrayWithoutMode(): void
    {
        $data = ['url' => self::VALID_RELAY];

        $ref = RelayReference::fromArray($data);

        $this->assertSame(self::VALID_RELAY, (string) $ref->getRelayUrl());
        $this->assertNull($ref->getMode());
    }

    public function testFromArrayThrowsForInvalidUrl(): void
    {
        $this->expectException(RuntimeException::class);

        RelayReference::fromArray(['url' => 'not-a-valid-url']);
    }

    public function testRoundTripThroughArray(): void
    {
        $relay = RelayUrl::fromString(self::VALID_RELAY) ?? throw new RuntimeException('Invalid test relay');
        $original = new RelayReference($relay, 'write');

        $recreated = RelayReference::fromArray($original->toArray());

        $this->assertSame((string) $original->getRelayUrl(), (string) $recreated->getRelayUrl());
        $this->assertSame($original->getMode(), $recreated->getMode());
    }
}
