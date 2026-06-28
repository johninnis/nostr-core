<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05IdentifierTest extends TestCase
{
    public function testFromStringParsesValidIdentifier(): void
    {
        $identifier = Nip05Identifier::fromString('alice@example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $identifier = Nip05Identifier::fromString(' alice @  example.com ') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
    }

    public function testFromStringCanonicalisesDomainToLowerCaseButPreservesLocalPart(): void
    {
        $identifier = Nip05Identifier::fromString('Alice@Example.COM') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('Alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
        $this->assertSame('Alice@example.com', (string) $identifier);
        $this->assertSame(
            'https://example.com/.well-known/nostr.json?name=Alice',
            $identifier->getWellKnownUrl(),
        );
    }

    public function testFromStringReturnsNullForMissingAtSymbol(): void
    {
        $this->assertNull(Nip05Identifier::fromString('aliceexample.com'));
    }

    public function testFromStringReturnsNullForEmptyLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::fromString('@example.com'));
    }

    public function testFromStringReturnsNullForEmptyDomain(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@'));
    }

    public function testGetWellKnownUrlReturnsCorrectFormat(): void
    {
        $identifier = Nip05Identifier::fromString('bob@relay.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame(
            'https://relay.example.com/.well-known/nostr.json?name=bob',
            $identifier->getWellKnownUrl()
        );
    }

    public function testToStringReturnsFullIdentifier(): void
    {
        $identifier = Nip05Identifier::fromString('alice@example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice@example.com', (string) $identifier);
    }

    public function testFromStringParsesNestedSubdomain(): void
    {
        $identifier = Nip05Identifier::fromString('user@sub.domain.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('user', $identifier->getLocalPart());
        $this->assertSame('sub.domain.example.com', $identifier->getDomain());
    }

    public function testFromStringReturnsNullForQueryParamInjectionInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice&admin=1@example.com'));
    }

    public function testFromStringReturnsNullForFragmentInjectionInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice#fragment@example.com'));
    }

    public function testFromStringReturnsNullForPathTraversalInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::fromString('../secrets@example.com'));
    }

    public function testFromStringReturnsNullForSpaceInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice bob@example.com'));
    }

    public function testFromStringReturnsNullForPathInDomain(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@example.com/../secrets'));
    }

    public function testFromStringReturnsNullForUserInfoInjectionInDomain(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@evil.com:8080@victim.com'));
    }

    public function testFromStringReturnsNullForIpv4Literal(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@169.254.169.254'));
    }

    public function testFromStringReturnsNullForIpv6Literal(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@[::1]'));
    }

    public function testFromStringReturnsNullForSingleLabelHostname(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@localhost'));
    }

    public function testFromStringReturnsNullForPortInDomain(): void
    {
        $this->assertNull(Nip05Identifier::fromString('alice@example.com:8080'));
    }

    public function testFromStringAcceptsPunycodeDomain(): void
    {
        $identifier = Nip05Identifier::fromString('alice@xn--nxasmq6b.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('xn--nxasmq6b.example.com', $identifier->getDomain());
    }

    public function testGetWellKnownUrlEncodesLocalPartDefensively(): void
    {
        $identifier = Nip05Identifier::fromString('alice.bob_42@example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame(
            'https://example.com/.well-known/nostr.json?name=alice.bob_42',
            $identifier->getWellKnownUrl()
        );
    }
}
