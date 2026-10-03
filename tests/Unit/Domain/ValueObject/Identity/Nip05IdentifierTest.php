<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testTryFromStringTrimsWhitespaceAroundTheWholeInput(): void
    {
        $identifier = Nip05Identifier::tryFromString(" \t alice@example.com \n") ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice@example.com', (string) $identifier);
    }

    public function testTryFromStringReturnsNullForWhitespaceBeforeTheAtSign(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice @example.com'));
    }

    public function testTryFromStringReturnsNullForWhitespaceAfterTheAtSign(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@ example.com'));
    }

    public function testTryFromStringReturnsNullForWhitespaceInsideTheDomain(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@example .com'));
    }

    #[DataProvider('ecmaScriptTrimmedCodePoints')]
    public function testTryFromStringTrimsEveryCharacterEcmaScriptTrimRemoves(int $codePoint): void
    {
        $space = mb_chr($codePoint, 'UTF-8');

        $identifier = Nip05Identifier::tryFromString($space.'alice@example.com'.$space);

        $this->assertSame('alice@example.com', (string) $identifier);
    }

    #[DataProvider('ecmaScriptTrimmedCodePoints')]
    public function testTryFromStringReturnsNullForAnEcmaScriptTrimmedCharacterInside(int $codePoint): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@'.mb_chr($codePoint, 'UTF-8').'example.com'));
    }

    #[DataProvider('codePointsEcmaScriptTrimKeeps')]
    public function testTryFromStringKeepsEveryCharacterEcmaScriptTrimKeeps(int $codePoint): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@example.com'.mb_chr($codePoint, 'UTF-8')));
    }

    public function testTryFromStringReturnsNullForInvalidUtf8(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString("alice@example.com\xFF"));
    }

    public function testTryFromStringReturnsNullForANonAsciiDomainThatLowerCasesIntoAscii(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString("alice@\u{212A}ey.example.com"));
    }

    public function testTryFromStringReturnsNullForADomainThatLowerCasesOutsideAscii(): void
    {
        $this->assertNull(Nip05Identifier::tryFromString("alice@\u{0130}.example.com"));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function ecmaScriptTrimmedCodePoints(): iterable
    {
        $codePoints = [
            0x0009, 0x000A, 0x000B, 0x000C, 0x000D, 0x0020, 0x00A0, 0x1680,
            0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200A,
            0x2028, 0x2029, 0x202F, 0x205F, 0x3000, 0xFEFF,
        ];

        foreach ($codePoints as $codePoint) {
            yield sprintf('U+%04X', $codePoint) => [$codePoint];
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function codePointsEcmaScriptTrimKeeps(): iterable
    {
        foreach ([0x0000, 0x001C, 0x001D, 0x001E, 0x001F, 0x0085, 0x180E, 0x200B, 0x200C, 0x200D, 0x2060] as $codePoint) {
            yield sprintf('U+%04X', $codePoint) => [$codePoint];
        }
    }

    public function testTryFromStringCanonicalisesDomainToLowerCase(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@Example.COM') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('alice', $identifier->getLocalPart());
        $this->assertSame('example.com', $identifier->getDomain());
        $this->assertSame('alice@example.com', (string) $identifier);
        $this->assertSame(
            'https://example.com/.well-known/nostr.json?name=alice',
            (string) $identifier->getWellKnownUrl(),
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
            (string) $identifier->getWellKnownUrl()
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

    #[DataProvider('domainsEndingInANumericLabel')]
    public function testTryFromStringReturnsNullForADomainWhoseLastLabelIsNumeric(string $domain): void
    {
        $this->assertNull(Nip05Identifier::tryFromString('alice@'.$domain));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function domainsEndingInANumericLabel(): iterable
    {
        yield 'shortened IPv4 loopback' => ['127.1'];
        yield 'hexadecimal IPv4 label' => ['0x7f.1'];
        yield 'out-of-range dotted quad' => ['256.1.1.1'];
        yield 'five dotted numbers' => ['1.2.3.4.5'];
        yield 'hostname with a numeric top label' => ['example.123'];
        yield 'hexadecimal last label' => ['127.0x1'];
        yield 'bare hexadecimal prefix as the last label' => ['example.0x'];
        yield 'dotted-decimal IPv4 address' => ['1.2.3.4'];
        yield 'upper-case hexadecimal last label' => ['127.0X1'];
    }

    public function testTryFromStringAcceptsANumericLabelBeforeTheLast(): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@123.example.com') ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame('123.example.com', $identifier->getDomain());
    }

    #[DataProvider('lastLabelsThatAreNotNumbers')]
    public function testTryFromStringAcceptsALastLabelThatIsNotANumber(string $domain): void
    {
        $identifier = Nip05Identifier::tryFromString('alice@'.$domain) ?? throw new RuntimeException('expected valid identifier');

        $this->assertSame($domain, $identifier->getDomain());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lastLabelsThatAreNotNumbers(): iterable
    {
        yield 'digits then a letter' => ['example.1a'];
        yield 'hexadecimal prefix then a non-hex letter' => ['example.0xg'];
        yield 'letter then a digit' => ['x.y0'];
        yield 'hexadecimal-looking first label' => ['0xg.com'];
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
            (string) $identifier->getWellKnownUrl()
        );
    }
}
