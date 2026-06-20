<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Infrastructure\Http\Nip11Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\NativeType;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class Nip11ClientTest extends TestCase
{
    public function testReturnsNullWhenHttpServiceReturnsNull(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn(null);

        $this->assertNull(
            $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'))
        );
    }

    public function testReturnsPopulatedInfoOnSuccessfulFetch(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'name' => 'Example Relay',
            'description' => 'A relay for examples',
            'pubkey' => 'abc123',
            'supported_nips' => [1, 11, 42],
            'software' => 'strfry',
            'version' => '1.0.0',
        ]);

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertSame('Example Relay', $info->getName());
        $this->assertSame('A relay for examples', $info->getDescription());
        $this->assertSame([1, 11, 42], $info->getSupportedNips());
        $this->assertSame('strfry', $info->getSoftware());
    }

    public function testGracefullyHandlesMalformedSupportedNips(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'name' => 'Broken Relay',
            'supported_nips' => 'not-an-array',
        ]);

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertSame('Broken Relay', $info->getName());
        $this->assertNull($info->getSupportedNips());
    }

    public function testGracefullyHandlesMalformedStringFields(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'name' => ['unexpected' => 'array'],
            'description' => 42,
        ]);

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
    }

    public function testReturnsInfoWithAllNullFieldsWhenResponseIsEmpty(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([]);

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getSupportedNips());
    }

    #[DataProvider('urlRewriteCases')]
    public function testRewritesWebSocketSchemeToHttpForFetch(string $inputUrl, string $expectedHttpUrl): void
    {
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('getJson')
            ->with($expectedHttpUrl, new IsType(NativeType::Array))
            ->willReturn([]);

        $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl($inputUrl));
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
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('getJson')
            ->with(
                $this->anything(),
                $this->callback(static fn (array $headers): bool => 'application/nostr+json' === ($headers['Accept'] ?? null))
            )
            ->willReturn([]);

        $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));
    }

    private function makeAdapter(HttpServiceInterface $httpService): Nip11Client
    {
        return new Nip11Client($httpService, new NullLogger());
    }

    private function relayUrl(string $url): RelayUrl
    {
        return RelayUrl::fromString($url)
            ?? throw new RuntimeException('Test setup: invalid relay URL '.$url);
    }
}
