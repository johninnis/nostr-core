<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05IdentifierTest extends TestCase
{
    public function testTryFromStringParsesValidIdentifier(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
    }

    public function testTryFromStringTrimsWhitespace(): void
    {
        $identifier = Nip05Identifier::tryFromString(' alice @  example.com ') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
    }

    public function testTryFromStringCanonicalisesDomainToLowerCase(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@Example.COM') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
        $this->assertSame('alice@example.com', (string) $identifier);
        $this->assertSame(
            'https://example.com/.well-known/nostr.json?name=alice',
            $identifier->getWellKnownUrl(),
        );
    }

    public function testTryFromStringRejectsUppercaseLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('Alice@example.com'));
    }

    public function testTryFromStringReturnsNullForMissingAtSymbol(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('aliceexample.com'));
    }

    public function testTryFromStringReturnsNullForEmptyLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('@example.com'));
    }

    public function testTryFromStringReturnsNullForEmptyDomain(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@'));
    }

    public function testGetWellKnownUrlReturnsCorrectFormat(): void
    {
        $identifier = Nip05Identifier::tryFromString('bob@relay.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame(
            'https://relay.example.com/.well-known/nostr.json?name=bob',
            $identifier->getWellKnownUrl()
        );
    }

    public function testToStringReturnsFullIdentifier(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice@example.com', (string) $identifier);
    }

    public function testTryFromStringParsesNestedSubdomain(): void
    {
        $identifier = Nip05Identifier::tryFromString('user@sub.domain.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('user', $identifier->getLocalPart());
        $this->assertSame('sub.domain.example.com', $identifier->getDomain());
    }

    public function testTryFromStringReturnsNullForQueryParamInjectionInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice&admin=1@example.com'));
    }

    public function testTryFromStringReturnsNullForFragmentInjectionInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice#fragment@example.com'));
    }

    public function testTryFromStringReturnsNullForPathTraversalInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('../secrets@example.com'));
    }

    public function testTryFromStringReturnsNullForSpaceInLocalPart(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice bob@example.com'));
    }

    public function testTryFromStringReturnsNullForPathInDomain(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@example.com/../secrets'));
    }

    public function testTryFromStringReturnsNullForUserInfoInjectionInDomain(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@evil.com:8080@victim.com'));
    }

    public function testTryFromStringReturnsNullForIpv4Literal(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@169.254.169.254'));
    }

    public function testTryFromStringReturnsNullForIpv6Literal(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@[::1]'));
    }

    public function testTryFromStringReturnsNullForSingleLabelHostname(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@localhost'));
    }

    public function testTryFromStringReturnsNullForPortInDomain(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@example.com:8080'));
    }

    public function testTryFromStringAcceptsPunycodeDomain(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@xn--nxasmq6b.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('xn--nxasmq6b.example.com', $identifier->getDomain());
    }

    public function testGetWellKnownUrlEncodesLocalPartDefensively(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice.bob_42@example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame(
            'https://example.com/.well-known/nostr.json?name=alice.bob_42',
            $identifier->getWellKnownUrl()
        );
    }
}
