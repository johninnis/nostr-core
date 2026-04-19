<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Adapter;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Infrastructure\Adapter\Nip11Adapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class Nip11AdapterTest extends TestCase
{
    private HttpServiceInterface&MockObject $httpService;
    private Nip11Adapter $adapter;

    protected function setUp(): void
    {
        $this->httpService = $this->createMock(HttpServiceInterface::class);
        $this->adapter = new Nip11Adapter($this->httpService, new NullLogger());
    }

    public function testReturnsNullWhenHttpServiceReturnsNull(): void
    {
        $this->httpService->method('getJson')->willReturn(null);

        $this->assertNull($this->adapter->fetchNip11Info($this->relayUrl('wss://relay.example.com')));
    }

    public function testReturnsPopulatedInfoOnSuccessfulFetch(): void
    {
        $this->httpService->method('getJson')->willReturn([
            'name' => 'Example Relay',
            'description' => 'A relay for examples',
            'pubkey' => 'abc123',
            'supported_nips' => [1, 11, 42],
            'software' => 'strfry',
            'version' => '1.0.0',
        ]);

        $info = $this->adapter->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertSame('Example Relay', $info->getName());
        $this->assertSame('A relay for examples', $info->getDescription());
        $this->assertSame([1, 11, 42], $info->getSupportedNips());
        $this->assertSame('strfry', $info->getSoftware());
    }

    public function testGracefullyHandlesMalformedSupportedNips(): void
    {
        $this->httpService->method('getJson')->willReturn([
            'name' => 'Broken Relay',
            'supported_nips' => 'not-an-array',
        ]);

        $info = $this->adapter->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertSame('Broken Relay', $info->getName());
        $this->assertNull($info->getSupportedNips());
    }

    public function testGracefullyHandlesMalformedStringFields(): void
    {
        $this->httpService->method('getJson')->willReturn([
            'name' => ['unexpected' => 'array'],
            'description' => 42,
        ]);

        $info = $this->adapter->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
    }

    public function testReturnsInfoWithAllNullFieldsWhenResponseIsEmpty(): void
    {
        $this->httpService->method('getJson')->willReturn([]);

        $info = $this->adapter->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getSupportedNips());
    }

    #[DataProvider('urlRewriteCases')]
    public function testRewritesWebSocketSchemeToHttpForFetch(string $inputUrl, string $expectedHttpUrl): void
    {
        $this->httpService
            ->expects($this->once())
            ->method('getJson')
            ->with($expectedHttpUrl, $this->isType('array'))
            ->willReturn([]);

        $this->adapter->fetchNip11Info($this->relayUrl($inputUrl));
    }

    public static function urlRewriteCases(): array
    {
        return [
            'wss to https' => ['wss://relay.example.com', 'https://relay.example.com'],
            'ws to http' => ['ws://relay.example.com', 'http://relay.example.com'],
        ];
    }

    public function testSendsNip11AcceptHeader(): void
    {
        $this->httpService
            ->expects($this->once())
            ->method('getJson')
            ->with(
                $this->anything(),
                $this->callback(static fn (array $headers): bool => 'application/nostr+json' === ($headers['Accept'] ?? null))
            )
            ->willReturn([]);

        $this->adapter->fetchNip11Info($this->relayUrl('wss://relay.example.com'));
    }

    private function relayUrl(string $url): RelayUrl
    {
        return RelayUrl::fromString($url)
            ?? throw new RuntimeException('Test setup: invalid relay URL '.$url);
    }
}
