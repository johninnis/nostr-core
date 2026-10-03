<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Service;

use Innis\Nostr\Core\Application\Port\HttpFetchFailure;
use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Application\Service\Nip05Verifier;
use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05VerifierTest extends TestCase
{
    private const string VALID_PUBKEY_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testReportsFetchFailedWhenTheDomainGivesNoAnswer(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn(HttpFetchFailure::NoAnswer);

        $failure = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::FetchFailed, $failure);
    }

    public function testReportsNameNotFoundWhenTheDomainAnswersNotFound(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn(HttpFetchFailure::NotFound);

        $failure = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testDelegatesSuccessfulMatchToDomainVerifier(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn('{"names":{"alice":"'.self::VALID_PUBKEY_HEX.'"}}');

        $failure = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertNull($failure);
    }

    public function testDelegatesFailureFromDomainVerifier(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn('{"names":{"bob":"'.self::VALID_PUBKEY_HEX.'"}}');

        $failure = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testReportsFetchFailedWhenTheBodyIsNotAJsonObject(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('get')->willReturn('[{"names":{"alice":"'.self::VALID_PUBKEY_HEX.'"}}]');

        $failure = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::FetchFailed, $failure);
    }

    public function testFetchesWellKnownUrlForIdentifier(): void
    {
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('get')
            ->with(HttpUrl::fromString('https://example.com/.well-known/nostr.json?name=alice'))
            ->willReturn(HttpFetchFailure::NoAnswer);

        $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());
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
            ->willReturn(HttpFetchFailure::NoAnswer);

        $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());
    }

    private function makeAdapter(HttpServiceInterface $httpService): Nip05Verifier
    {
        return new Nip05Verifier($httpService);
    }

    private function identifier(): Nip05Identifier
    {
        return Nip05Identifier::tryFromString('alice@example.com')
            ?? throw new RuntimeException('Test setup: invalid identifier');
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::tryFromHex(self::VALID_PUBKEY_HEX)
            ?? throw new RuntimeException('Test setup: invalid pubkey hex');
    }
}
