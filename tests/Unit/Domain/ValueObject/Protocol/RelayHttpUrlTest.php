<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayHttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayHttpUrlTest extends TestCase
{
    private function relayUrl(string $url): RelayUrl
    {
        return RelayUrl::fromString($url)
            ?? throw new RuntimeException('Invalid test URL: '.$url);
    }

    public function testWssMapsToHttps(): void
    {
        $httpUrl = new RelayHttpUrl($this->relayUrl('wss://relay.example.com'));

        $this->assertSame('https://relay.example.com', (string) $httpUrl);
    }

    public function testWsMapsToHttp(): void
    {
        $httpUrl = new RelayHttpUrl($this->relayUrl('ws://localhost:7777'));

        $this->assertSame('http://localhost:7777', (string) $httpUrl);
    }

    public function testPreservesPortAndPath(): void
    {
        $httpUrl = new RelayHttpUrl($this->relayUrl('wss://relay.example.com:8080/nostr'));

        $this->assertSame('https://relay.example.com:8080/nostr', (string) $httpUrl);
    }
}
