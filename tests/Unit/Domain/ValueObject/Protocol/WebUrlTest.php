<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\WebUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebUrlTest extends TestCase
{
    public function testAUrlWithAnHttpUrlFormIsKeptExactlyAsWritten(): void
    {
        $this->assertSame('HTTPS://Example.com:443/a.png#x', (string) WebUrl::fromString('HTTPS://Example.com:443/a.png#x'));
    }

    public function testAUrlWithoutAPathIsKeptWithoutOne(): void
    {
        $this->assertSame('https://x.example', (string) WebUrl::fromString('https://x.example'));
    }

    public function testAHashRouteIsKeptAsWritten(): void
    {
        $this->assertSame('https://x.example/#/post/1?a=B', (string) WebUrl::fromString('https://x.example/#/post/1?a=B'));
    }

    #[DataProvider('urlsWithNoHttpUrlForm')]
    public function testAUrlWithNoHttpUrlFormIsNoWebUrl(mixed $url): void
    {
        $this->assertNull(WebUrl::tryFromString($url));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function urlsWithNoHttpUrlForm(): iterable
    {
        yield 'empty' => [''];
        yield 'another scheme' => ['ftp://example.com/a.png'];
        yield 'a websocket URL' => ['wss://relay.example.com/'];
        yield 'a script URL with a fragment' => ['javascript:alert(1)#top'];
        yield 'credentials' => ['https://user@example.com/a.png'];
        yield 'a control character' => ["https://example.com/a\u{85}.png"];
        yield 'a control character in the fragment' => ["https://x.example/a#\u{85}"];
        yield 'not a string' => [42];
    }

    public function testTwoSpellingsOfOneUrlAreDifferentWebUrls(): void
    {
        $this->assertFalse(WebUrl::fromString('https://example.com/a.png')->equals(WebUrl::fromString('HTTPS://example.com/a.png')));
    }

    public function testUrlsDifferingOnlyByFragmentAreDifferentWebUrls(): void
    {
        $this->assertFalse(WebUrl::fromString('https://x.example/#/post/1')->equals(WebUrl::fromString('https://x.example/#/post/2')));
    }

    public function testTheSameTextIsTheSameWebUrl(): void
    {
        $this->assertTrue(WebUrl::fromString('https://example.com/a.png')->equals(WebUrl::fromString('https://example.com/a.png')));
    }

    public function testItsIdentityKeyIsTheUrlAsWritten(): void
    {
        $this->assertSame('HTTPS://Mint.example', WebUrl::fromString('HTTPS://Mint.example')->identityKey());
    }

    public function testFromStringThrowsOnAUrlWithNoHttpUrlForm(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WebUrl::fromString('data:image/png;base64,AAAA');
    }
}
