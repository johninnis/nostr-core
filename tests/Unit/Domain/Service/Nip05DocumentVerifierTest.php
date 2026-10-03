<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use Innis\Nostr\Core\Domain\Service\Nip05DocumentVerifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05DocumentVerifierTest extends TestCase
{
    private const string VALID_PUBKEY_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testReturnsMissingNamesWhenDocumentLacksNamesKey(): void
    {
        $failure = Nip05DocumentVerifier::verify(self::document(['relays' => []]), $this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::MissingNames, $failure);
    }

    public function testReturnsNameNotFoundWhenNamesIsNotAnArray(): void
    {
        $failure = Nip05DocumentVerifier::verify(self::document(['names' => 'nope']), $this->identifier(), $this->pubkey());

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testReturnsNameNotFoundWhenLocalPartIsAbsent(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['bob' => self::VALID_PUBKEY_HEX]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testReturnsPubkeyMismatchWhenReturnedPubkeyDiffers(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['alice' => str_repeat('f', 64)]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::PubkeyMismatch, $failure);
    }

    public function testReturnsPubkeyMismatchWhenReturnedPubkeyIsNotAString(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['alice' => 123]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::PubkeyMismatch, $failure);
    }

    public function testReturnsNullWhenLocalPartMapsToExpectedPubkey(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['alice' => self::VALID_PUBKEY_HEX]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertNull($failure);
    }

    public function testReturnsPubkeyMismatchWhenReturnedPubkeyIsUpperCaseHex(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['alice' => strtoupper(self::VALID_PUBKEY_HEX)]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::PubkeyMismatch, $failure);
    }

    public function testReturnsPubkeyMismatchWhenReturnedPubkeyIsMixedCaseHex(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['alice' => str_replace('a', 'A', self::VALID_PUBKEY_HEX)]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::PubkeyMismatch, $failure);
    }

    public function testReturnsNameNotFoundWhenOnlyAnUpperCaseSpellingOfTheNameIsListed(): void
    {
        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['ALICE' => self::VALID_PUBKEY_HEX]]),
            $this->identifier(),
            $this->pubkey(),
        );

        $this->assertSame(Nip05VerificationFailure::NameNotFound, $failure);
    }

    public function testVerifiesTheSpecExampleDocument(): void
    {
        $pubkey = PublicKey::tryFromHex('b0635d6a9851d3aed0cd6c495b282167acf761729078d975fc341b22650b07b9')
            ?? throw new RuntimeException('Test setup: invalid pubkey hex');
        $identifier = Nip05Identifier::tryFromString('bob@example.com')
            ?? throw new RuntimeException('Test setup: invalid identifier');

        $failure = Nip05DocumentVerifier::verify(
            self::document(['names' => ['bob' => 'b0635d6a9851d3aed0cd6c495b282167acf761729078d975fc341b22650b07b9']]),
            $identifier,
            $pubkey,
        );

        $this->assertNull($failure);
    }

    #[DataProvider('bodiesThatAreNotAJsonObject')]
    public function testReportsNoAnswerWhenTheBodyIsNotAJsonObject(string $body): void
    {
        $this->assertSame(Nip05VerificationFailure::FetchFailed, Nip05DocumentVerifier::verify($body, $this->identifier(), $this->pubkey()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodiesThatAreNotAJsonObject(): iterable
    {
        yield 'not JSON' => ['<html>not found</html>'];
        yield 'empty' => [''];
        yield 'a JSON array' => ['[{"names":{"alice":"'.self::VALID_PUBKEY_HEX.'"}}]'];
        yield 'an empty JSON array' => ['[]'];
        yield 'a JSON string' => ['"names"'];
        yield 'JSON null' => ['null'];
    }

    public function testReturnsMissingNamesForAnEmptyJsonObject(): void
    {
        $this->assertSame(Nip05VerificationFailure::MissingNames, Nip05DocumentVerifier::verify('{}', $this->identifier(), $this->pubkey()));
    }

    #[DataProvider('namesThatAreNotAJsonObject')]
    public function testReturnsNameNotFoundWhenNamesIsNotAJsonObject(string $names): void
    {
        $identifier = Nip05Identifier::tryFromString('0@example.com') ?? throw new RuntimeException('Test setup: invalid identifier');

        $this->assertSame(Nip05VerificationFailure::NameNotFound, Nip05DocumentVerifier::verify('{"names":'.$names.'}', $identifier, $this->pubkey()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesThatAreNotAJsonObject(): iterable
    {
        yield 'a JSON array whose first entry is the key' => ['["'.self::VALID_PUBKEY_HEX.'"]'];
        yield 'a number' => ['1'];
        yield 'true' => ['true'];
    }

    public function testFindsANameKeyedLikeAListIndex(): void
    {
        $identifier = Nip05Identifier::tryFromString('0@example.com') ?? throw new RuntimeException('Test setup: invalid identifier');

        $this->assertNull(Nip05DocumentVerifier::verify('{"names":{"0":"'.self::VALID_PUBKEY_HEX.'"}}', $identifier, $this->pubkey()));
    }

    public function testReturnsNameNotFoundForAnEmptyNamesObject(): void
    {
        $this->assertSame(Nip05VerificationFailure::NameNotFound, Nip05DocumentVerifier::verify('{"names":{}}', $this->identifier(), $this->pubkey()));
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function document(array $document): string
    {
        return json_encode($document, JSON_THROW_ON_ERROR);
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
