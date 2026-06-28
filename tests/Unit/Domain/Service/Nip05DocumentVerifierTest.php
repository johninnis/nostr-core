<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\Service\Nip05DocumentVerifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05DocumentVerifierTest extends TestCase
{
    private const VALID_PUBKEY_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testReturnsMissingNamesWhenDocumentLacksNamesKey(): void
    {
        $failure = Nip05DocumentVerifier::verify(['relays' => []], $this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::MissingNames, $failure);
    }

    public function testReturnsNameNotFoundWhenNamesIsNotAnArray(): void
    {
        $failure = Nip05DocumentVerifier::verify(['names' => 'nope'], $this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testReturnsNameNotFoundWhenLocalPartIsAbsent(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            ['names' => ['bob' => self::VALID_PUBKEY_HEX]],
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testReturnsPubkeyMismatchWhenReturnedPubkeyDiffers(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            ['names' => ['alice' => str_repeat('f', 64)]],
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::PubkeyMismatch, $failure);
    }

    public function testReturnsPubkeyMismatchWhenReturnedPubkeyIsNotAString(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            ['names' => ['alice' => 123]],
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::PubkeyMismatch, $failure);
    }

    public function testReturnsNullWhenLocalPartMapsToExpectedPubkey(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            ['names' => ['alice' => self::VALID_PUBKEY_HEX]],
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertNull($failure);
    }

    public function testMatchesPubkeyCaseInsensitively(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            ['names' => ['alice' => strtoupper(self::VALID_PUBKEY_HEX)]],
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertNull($failure);
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
