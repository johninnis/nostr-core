<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\NutzapVerificationFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NutzapVerificationFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(NutzapVerificationFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{NutzapVerificationFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'recipient mismatch' => [NutzapVerificationFailure::RecipientMismatch, 'recipient_mismatch'];
        yield 'mint not listed' => [NutzapVerificationFailure::MintNotListed, 'mint_not_listed'];
        yield 'no proof' => [NutzapVerificationFailure::NoProof, 'no_proof'];
        yield 'proof not locked to recipient' => [NutzapVerificationFailure::ProofNotLockedToRecipient, 'proof_not_locked_to_recipient'];
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame(
            'A nutzap proof is not locked to the recipient\'s P2PK key alone',
            NutzapVerificationFailure::ProofNotLockedToRecipient->message(),
        );
    }

    #[DataProvider('everyCase')]
    public function testEveryCaseHasAMessageApartFromItsCode(NutzapVerificationFailure $failure): void
    {
        $this->assertNotSame($failure->value, $failure->message());
    }

    /**
     * @return iterable<string, array{NutzapVerificationFailure}>
     */
    public static function everyCase(): iterable
    {
        foreach (NutzapVerificationFailure::cases() as $failure) {
            yield $failure->value => [$failure];
        }
    }
}
