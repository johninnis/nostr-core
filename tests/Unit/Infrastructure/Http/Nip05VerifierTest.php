<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Http;

use Innis\Nostr\Core\Application\Port\HttpServiceInterface;
use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailureReason;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Http\Nip05Verifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05VerifierTest extends TestCase
{
    private const VALID_PUBKEY_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testReturnsFailureWhenHttpServiceReturnsNull(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn(null);

        $result = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertFalse($result->isValid());
        $this->assertSame(Nip05VerificationFailureReason::FetchFailed, $result->getFailureReason());
    }

    public function testReturnsFailureWhenResponseLacksNamesKey(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn(['relays' => []]);

        $result = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertFalse($result->isValid());
        $this->assertSame(Nip05VerificationFailureReason::MissingNames, $result->getFailureReason());
    }

    public function testReturnsFailureWhenLocalPartNotInNames(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'names' => [
                'bob' => self::VALID_PUBKEY_HEX,
            ],
        ]);

        $result = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertFalse($result->isValid());
        $this->assertSame(Nip05VerificationFailureReason::NameNotFound, $result->getFailureReason());
    }

    public function testReturnsFailureWhenReturnedPubkeyDoesNotMatch(): void
    {
        $differentPubkey = str_repeat('f', 64);
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'names' => [
                'alice' => $differentPubkey,
            ],
        ]);

        $result = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertFalse($result->isValid());
        $this->assertSame(Nip05VerificationFailureReason::PubkeyMismatch, $result->getFailureReason());
    }

    public function testReturnsSuccessWhenNamesMatchExpectedPubkey(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'names' => [
                'alice' => self::VALID_PUBKEY_HEX,
            ],
        ]);

        $result = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getFailureReason());
    }

    public function testReturnsSuccessWhenReturnedPubkeyDiffersOnlyByCase(): void
    {
        $httpService = $this->createStub(HttpServiceInterface::class);
        $httpService->method('getJson')->willReturn([
            'names' => [
                'alice' => strtoupper(self::VALID_PUBKEY_HEX),
            ],
        ]);

        $result = $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getFailureReason());
    }

    public function testFetchesWellKnownUrlForIdentifier(): void
    {
        $httpService = $this->createMock(HttpServiceInterface::class);
        $httpService
            ->expects($this->once())
            ->method('getJson')
            ->with('https://example.com/.well-known/nostr.json?name=alice')
            ->willReturn(null);

        $this->makeAdapter($httpService)->verify($this->identifier(), $this->pubkey());
    }

    private function makeAdapter(HttpServiceInterface $httpService): Nip05Verifier
    {
        return new Nip05Verifier($httpService);
    }

    private function identifier(): Nip05Identifier
    {
        return Nip05Identifier::fromString('alice@example.com')
            ?? throw new RuntimeException('Test setup: invalid identifier');
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::fromHex(self::VALID_PUBKEY_HEX)
            ?? throw new RuntimeException('Test setup: invalid pubkey hex');
    }
}
