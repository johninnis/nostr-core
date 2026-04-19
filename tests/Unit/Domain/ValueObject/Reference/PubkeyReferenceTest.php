<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Exception\InvalidReferenceException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PubkeyReferenceTest extends TestCase
{
    private const VALID_PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const VALID_RELAY = 'wss://relay.example.com';

    public function testConstructorWithPubkeyOnly(): void
    {
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $ref = new PubkeyReference($pubkey);

        $this->assertSame($pubkey, $ref->getPubkey());
        $this->assertNull($ref->getRelayUrl());
        $this->assertNull($ref->getPetname());
    }

    public function testConstructorWithAllParameters(): void
    {
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $relay = RelayUrl::fromString(self::VALID_RELAY);
        $ref = new PubkeyReference($pubkey, $relay, 'alice');

        $this->assertSame($pubkey, $ref->getPubkey());
        $this->assertSame($relay, $ref->getRelayUrl());
        $this->assertSame('alice', $ref->getPetname());
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $relay = RelayUrl::fromString(self::VALID_RELAY);
        $ref = new PubkeyReference($pubkey, $relay, 'alice');

        $array = $ref->toArray();
        $this->assertSame(self::VALID_PUBKEY, $array['pubkey']);
        $this->assertSame(self::VALID_RELAY, $array['relay_url']);
        $this->assertSame('alice', $array['petname']);
    }

    public function testToArrayWithNullOptionalFields(): void
    {
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $ref = new PubkeyReference($pubkey);

        $array = $ref->toArray();
        $this->assertSame(self::VALID_PUBKEY, $array['pubkey']);
        $this->assertNull($array['relay_url']);
        $this->assertNull($array['petname']);
    }

    public function testFromArrayCreatesValidReference(): void
    {
        $data = [
            'pubkey' => self::VALID_PUBKEY,
            'relay_url' => self::VALID_RELAY,
            'petname' => 'bob',
        ];

        $ref = PubkeyReference::fromArray($data);

        $this->assertSame(self::VALID_PUBKEY, $ref->getPubkey()->toHex());
        $this->assertNotNull($ref->getRelayUrl());
        $this->assertSame(self::VALID_RELAY, (string) $ref->getRelayUrl());
        $this->assertSame('bob', $ref->getPetname());
    }

    public function testFromArrayWithoutOptionalFields(): void
    {
        $data = ['pubkey' => self::VALID_PUBKEY];

        $ref = PubkeyReference::fromArray($data);

        $this->assertSame(self::VALID_PUBKEY, $ref->getPubkey()->toHex());
        $this->assertNull($ref->getRelayUrl());
        $this->assertNull($ref->getPetname());
    }

    public function testFromArrayThrowsForInvalidPubkey(): void
    {
        $this->expectException(InvalidReferenceException::class);

        PubkeyReference::fromArray(['pubkey' => 'invalid']);
    }

    public function testRoundTripThroughArray(): void
    {
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
        $relay = RelayUrl::fromString(self::VALID_RELAY);
        $original = new PubkeyReference($pubkey, $relay, 'alice');

        $recreated = PubkeyReference::fromArray($original->toArray());

        $this->assertSame($original->getPubkey()->toHex(), $recreated->getPubkey()->toHex());
        $this->assertSame((string) $original->getRelayUrl(), (string) $recreated->getRelayUrl());
        $this->assertSame($original->getPetname(), $recreated->getPetname());
    }
}
