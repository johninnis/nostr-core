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

    public function testFromStringRejectsQueryParamInjectionInLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('local part contains disallowed characters');

        Nip05Identifier::fromString('alice&admin=1@example.com');
    }

    public function testFromStringRejectsFragmentInjectionInLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('local part contains disallowed characters');

        Nip05Identifier::fromString('alice#fragment@example.com');
    }

    public function testFromStringRejectsPathTraversalInLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('local part contains disallowed characters');

        Nip05Identifier::fromString('../secrets@example.com');
    }

    public function testFromStringRejectsSpaceInLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('local part contains disallowed characters');

        Nip05Identifier::fromString('alice bob@example.com');
    }

    public function testFromStringRejectsPathInDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('domain is not a valid hostname');

        Nip05Identifier::fromString('alice@example.com/../secrets');
    }

    public function testFromStringRejectsUserInfoInjectionInDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('domain is not a valid hostname');

        Nip05Identifier::fromString('alice@evil.com:8080@victim.com');
    }

    public function testFromStringRejectsIpv4Literal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('domain must be a hostname, not an IP literal');

        Nip05Identifier::fromString('alice@169.254.169.254');
    }

    public function testFromStringRejectsIpv6Literal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Nip05Identifier::fromString('alice@[::1]');
    }

    public function testFromStringRejectsSingleLabelHostname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('domain is not a valid hostname');

        Nip05Identifier::fromString('alice@localhost');
    }

    public function testFromStringRejectsPortInDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('domain is not a valid hostname');

        Nip05Identifier::fromString('alice@example.com:8080');
    }

    public function testFromStringAcceptsPunycodeDomain(): void
    {
        $identifier = Nip05Identifier::fromString('alice@xn--nxasmq6b.example.com');

        $this->assertSame('xn--nxasmq6b.example.com', $identifier->getDomain());
    }

    public function testGetWellKnownUrlEncodesLocalPartDefensively(): void
    {
        $identifier = Nip05Identifier::fromString('alice.bob_42@example.com');

        $this->assertSame(
            'https://example.com/.well-known/nostr.json?name=alice.bob_42',
            $identifier->getWellKnownUrl()
        );
    }
}
