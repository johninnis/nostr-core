<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip42ValidationFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(Nip42ValidationFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    #[DataProvider('caseMessages')]
    public function testEachFailureNamesTheRuleThatFailed(Nip42ValidationFailure $failure, string $message): void
    {
        $this->assertSame($message, $failure->message());
    }

    /**
     * @return iterable<string, array{Nip42ValidationFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'wrong kind' => [Nip42ValidationFailure::WrongKind, 'wrong_kind'];
        yield 'challenge mismatch' => [Nip42ValidationFailure::ChallengeMismatch, 'challenge_mismatch'];
        yield 'relay mismatch' => [Nip42ValidationFailure::RelayMismatch, 'relay_mismatch'];
        yield 'timestamp outside tolerance' => [Nip42ValidationFailure::TimestampOutsideTolerance, 'timestamp_outside_tolerance'];
    }

    /**
     * @return iterable<string, array{Nip42ValidationFailure, string}>
     */
    public static function caseMessages(): iterable
    {
        yield 'wrong kind' => [Nip42ValidationFailure::WrongKind, 'Event must be kind 22242'];
        yield 'challenge mismatch' => [Nip42ValidationFailure::ChallengeMismatch, 'Challenge does not match the one issued'];
        yield 'relay mismatch' => [Nip42ValidationFailure::RelayMismatch, 'Relay URL does not match this relay'];
        yield 'timestamp outside tolerance' => [Nip42ValidationFailure::TimestampOutsideTolerance, 'Event timestamp is outside tolerance'];
    }
}
