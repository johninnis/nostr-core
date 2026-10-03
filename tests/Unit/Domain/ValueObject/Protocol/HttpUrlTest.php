<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpUrlTest extends TestCase
{
    #[DataProvider('spellingsOfOneUrl')]
    public function testEquivalentSpellingsCanonicaliseToOneForm(string $spelling): void
    {
        $this->assertSame('https://relay.example.com/api?x=1', (string) HttpUrl::fromString($spelling));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spellingsOfOneUrl(): iterable
    {
        yield 'canonical' => ['https://relay.example.com/api?x=1'];
        yield 'upper-case scheme and host' => ['HTTPS://Relay.Example.COM/api?x=1'];
        yield 'default port' => ['https://relay.example.com:443/api?x=1'];
        yield 'fragment' => ['https://relay.example.com/api?x=1#top'];
        yield 'user info' => ['https://user:pass@relay.example.com/api?x=1'];
    }

    public function testAnEmptyPathIsTheRootPath(): void
    {
        $this->assertTrue(HttpUrl::fromString('http://example.com')->equals(HttpUrl::fromString('http://example.com/')));
    }

    public function testANonDefaultPortIsKept(): void
    {
        $this->assertSame('http://example.com:8080/', (string) HttpUrl::fromString('http://example.com:8080'));
    }

    public function testADifferentQueryIsADifferentUrl(): void
    {
        $this->assertFalse(HttpUrl::fromString('https://example.com/?a=1')->equals(HttpUrl::fromString('https://example.com/?a=2')));
    }

    public function testAHostOfEveryCharacterRfc3986AllowsInANameIsKept(): void
    {
        $this->assertSame("https://a-b_c~d!$&'()*+,;=%4a.example/a", (string) HttpUrl::fromString("https://A-b_c~d!$&'()*+,;=%4A.example/a"));
    }

    #[DataProvider('notHttpUrls')]
    public function testTryFromStringRefusesWhatIsNotAnHttpUrl(mixed $value): void
    {
        $this->assertNull(HttpUrl::tryFromString($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notHttpUrls(): iterable
    {
        yield 'no scheme or host' => ['garbage'];
        yield 'relative path' => ['/api'];
        yield 'websocket scheme' => ['wss://relay.example.com/'];
        yield 'no host' => ['http://:/bad'];
        yield 'not a string' => [42];
        yield 'not UTF-8' => ["https://example.com/\xFF"];
        yield 'a C1 control character' => ["https://example.com/a\u{85}b"];
        yield 'an IP literal that is not an IPv6 address' => ['https://[zz]/a'];
        yield 'an embedded IPv4 address that is not the last 32 bits' => ['https://[1.2.3.4::]/a'];
        yield 'an embedded IPv4 address before further groups' => ['https://[::1.2.3.4:1]/a'];
        yield 'a host with a space' => ['https://exa mple.com/a'];
        yield 'a host with a backslash' => ['https://example.com\\a'];
        yield 'a host with a character RFC 3986 reserves for no part of a name' => ['https://exa|mple.com/a'];
        yield 'a host with a malformed percent escape' => ['https://exa%zzmple.com/a'];
        yield 'a host with a non-ASCII letter' => ['https://bücher.example/a'];
    }

    public function testFromStringThrowsOnWhatIsNotAnHttpUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpUrl::fromString('garbage');
    }
}
