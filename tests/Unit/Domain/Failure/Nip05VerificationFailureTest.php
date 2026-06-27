<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip05VerificationFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(Nip05VerificationFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{Nip05VerificationFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'fetch failed' => [Nip05VerificationFailure::FetchFailed, 'fetch_failed'];
        yield 'missing names' => [Nip05VerificationFailure::MissingNames, 'missing_names'];
        yield 'name not found' => [Nip05VerificationFailure::NameNotFound, 'name_not_found'];
        yield 'pubkey mismatch' => [Nip05VerificationFailure::PubkeyMismatch, 'pubkey_mismatch'];
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame(
            'Pubkey in the NIP-05 document does not match the expected pubkey',
            Nip05VerificationFailure::PubkeyMismatch->message(),
        );
    }

    public function testEveryCaseSeparatesItsCodeFromItsMessage(): void
    {
        foreach (Nip05VerificationFailure::cases() as $failure) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $failure->value);
            $this->assertNotSame('', $failure->message());
            $this->assertNotSame($failure->value, $failure->message());
        }
    }
}
