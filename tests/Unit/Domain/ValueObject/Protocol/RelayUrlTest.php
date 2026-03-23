<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;

final class RelayUrlTest extends TestCase
{
    public function testValidWssUrl(): void
    {
        $url = RelayUrl::fromString('wss://relay.damus.io');

        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testValidWsUrl(): void
    {
        $url = RelayUrl::fromString('ws://localhost:7777');

        $this->assertSame('ws://localhost:7777', (string) $url);
    }

    public function testValidUrlWithPort(): void
    {
        $url = RelayUrl::fromString('wss://host.example.com:8080');

        $this->assertSame('wss://host.example.com:8080', (string) $url);
    }

    public function testValidUrlWithPath(): void
    {
        $url = RelayUrl::fromString('wss://host.example.com/nostr');

        $this->assertSame('wss://host.example.com/nostr', (string) $url);
    }

    public function testNormalisationStripsTrailingComma(): void
    {
        $url = RelayUrl::fromString('wss://relay.snort.social/,');

        $this->assertSame('wss://relay.snort.social', (string) $url);
    }

    public function testNormalisationStripsTrailingPeriod(): void
    {
        $url = RelayUrl::fromString('wss://relay.damus.io/.');

        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testNormalisationStripsTrailingSemicolon(): void
    {
        $url = RelayUrl::fromString('wss://relay.damus.io/;');

        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testNormalisationStripsTrailingSlash(): void
    {
        $url = RelayUrl::fromString('wss://relay.damus.io/');

        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testNormalisationLowercasesHost(): void
    {
        $url = RelayUrl::fromString('wss://Relay.Damus.IO');

        $this->assertSame('wss://relay.damus.io', (string) $url);
    }

    public function testRejectsUnicodeHost(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://⬤ wss//nostr-pub.wellorder.net'));
    }

    public function testRejectsHostnameInPath(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.snort.social/relay.snort.social'));
    }

    public function testRejectsDoubleSlashesInPath(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com//bad'));
    }

    public function testRejectsFragment(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com/#fragment'));
    }

    public function testRejectsPortOutOfRange(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com:0'));
    }

    public function testRejectsPortAboveMax(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com:70000'));
    }

    public function testRejectsSpacesInHost(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay example.com'));
    }

    public function testRejectsHostStartingWithDot(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://.relay.example.com'));
    }

    public function testRejectsHostEndingWithDot(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com.'));
    }

    public function testFromStringReturnsNullForInvalid(): void
    {
        $this->assertNull(RelayUrl::fromString('not-a-url'));
    }

    public function testFromStringReturnsNullForNull(): void
    {
        $this->assertNull(RelayUrl::fromString(null));
    }

    public function testFromStringReturnsInstanceForValid(): void
    {
        $result = RelayUrl::fromString('wss://relay.damus.io');

        $this->assertInstanceOf(RelayUrl::class, $result);
        $this->assertSame('wss://relay.damus.io', (string) $result);
    }

    public function testValidIpAddress(): void
    {
        $url = RelayUrl::fromString('wss://127.0.0.1:8080');

        $this->assertSame('wss://127.0.0.1:8080', (string) $url);
    }

    public function testRejectsConcatenatedUrls(): void
    {
        $this->assertNull(RelayUrl::fromString('wss://relay.example.com/wss://other.relay.com'));
    }
}
