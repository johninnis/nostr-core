<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Service;

use Innis\Nostr\Core\Application\Port\HttpFetchFailure;
use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Service\Nip11Fetcher;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\NativeType;
use PHPUnit\Framework\TestCase;

final class Nip11FetcherTest extends TestCase
{
    #[DataProvider('fetchFailures')]
    public function testReturnsNullWhenTheRelayServesNoDocument(HttpFetchFailure $failure): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn($failure);

        $this->assertNull(
            $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'))
        );
    }

    /**
     * @return iterable<string, array{HttpFetchFailure}>
     */
    public static function fetchFailures(): iterable
    {
        yield 'not found' => [HttpFetchFailure::NotFound];
        yield 'no answer' => [HttpFetchFailure::NoAnswer];
    }

    public function testReturnsPopulatedInfoOnSuccessfulFetch(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn('{"name":"Example Relay","description":"A relay for examples","pubkey":"abc123","supported_nips":[1,11,42],"software":"strfry","version":"1.0.0"}');

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
        $httpService->method('get')->willReturn('{"name":"Broken Relay","supported_nips":"not-an-array"}');

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertSame('Broken Relay', $info->getName());
        $this->assertNull($info->getSupportedNips());
    }

    public function testGracefullyHandlesMalformedStringFields(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn('{"name":{"unexpected":"array"},"description":42}');

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
    }

    public function testReturnsInfoWithAllNullFieldsWhenResponseIsEmpty(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn('{}');

        $info = $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getSupportedNips());
    }

    #[DataProvider('bodiesThatAreNotAJsonObject')]
    public function testReturnsNullWhenTheBodyIsNotAJsonObject(string $body): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn($body);

        $this->assertNull($this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodiesThatAreNotAJsonObject(): iterable
    {
        yield 'not JSON' => ['<html>relay</html>'];
        yield 'a JSON array' => ['[{"name":"Example Relay"}]'];
        yield 'an empty JSON array' => ['[]'];
        yield 'JSON null' => ['null'];
    }

    #[DataProvider('urlRewriteCases')]
    public function testRewritesWebSocketSchemeToHttpForFetch(string $inputUrl, string $expectedHttpUrl): void
    {
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('get')
            ->with(HttpUrl::fromString($expectedHttpUrl), new IsType(NativeType::Array))
            ->willReturn('{}');

        $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl($inputUrl));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urlRewriteCases(): array
    {
        return [
            'wss to https' => ['wss://relay.example.com', 'https://relay.example.com/'],
            'ws to http' => ['ws://relay.example.com', 'http://relay.example.com/'],
        ];
    }

    public function testSendsNip11AcceptHeader(): void
    {
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->anything(),
                $this->callback(static fn (array $headers): bool => 'application/nostr+json' === ($headers['Accept'] ?? null))
            )
            ->willReturn('{}');

        $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));
    }

    public function testIdentifiesItselfWithTheLibraryUserAgent(): void
    {
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->anything(),
                $this->callback(static fn (array $headers): bool => HttpServiceInterface::USER_AGENT === ($headers['User-Agent'] ?? null))
            )
            ->willReturn('{}');

        $this->makeAdapter($httpService)->fetchNip11Info($this->relayUrl('wss://relay.example.com'));
    }

    private function makeAdapter(HttpServiceInterface $httpService): Nip11Fetcher
    {
        return new Nip11Fetcher($httpService);
    }

    private function relayUrl(string $url): RelayUrl
    {
        return RelayUrl::fromString($url);
    }
}
