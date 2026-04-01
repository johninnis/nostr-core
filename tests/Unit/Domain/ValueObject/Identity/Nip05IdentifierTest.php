<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Nip05IdentifierTest extends TestCase
{
    public function testFromStringParsesValidIdentifier(): void
    {
        $identifier = Nip05Identifier::fromString('alice@example.com');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $identifier = Nip05Identifier::fromString(' alice @  example.com ');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
    }

    public function testFromStringThrowsForMissingAtSymbol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NIP-05 identifier format');

        Nip05Identifier::fromString('aliceexample.com');
    }

    public function testFromStringThrowsForEmptyLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NIP-05 identifier cannot have empty local part or domain');

        Nip05Identifier::fromString('@example.com');
    }

    public function testFromStringThrowsForEmptyDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NIP-05 identifier cannot have empty local part or domain');

        Nip05Identifier::fromString('alice@');
    }

    public function testGetWellKnownUrlReturnsCorrectFormat(): void
    {
        $identifier = Nip05Identifier::fromString('bob@relay.example.com');

        $this->assertSame(
            'https://relay.example.com/.well-known/nostr.json?name=bob',
            $identifier->getWellKnownUrl()
        );
    }

    public function testToStringReturnsFullIdentifier(): void
    {
        $identifier = Nip05Identifier::fromString('alice@example.com');

        $this->assertSame('alice@example.com', (string) $identifier);
    }

    public function testConstructorSetsProperties(): void
    {
        $identifier = new Nip05Identifier('carol', 'nostr.band');

        $this->assertSame('carol', $identifier->getLocalPart());
        $this->assertSame('nostr.band', $identifier->getDomain());
    }

    public function testFromStringWithOnlyOneAtSymbol(): void
    {
        $identifier = Nip05Identifier::fromString('user@sub.domain.example.com');

        $this->assertSame('user', $identifier->getLocalPart());
        $this->assertSame('sub.domain.example.com', $identifier->getDomain());
    }
}
