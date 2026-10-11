<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\NutzapDleqFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NutzapDleqFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(NutzapDleqFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{NutzapDleqFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'no proof' => [NutzapDleqFailure::NoProof, 'no_proof'];
        yield 'unknown keyset' => [NutzapDleqFailure::UnknownKeyset, 'unknown_keyset'];
        yield 'unit mismatch' => [NutzapDleqFailure::UnitMismatch, 'unit_mismatch'];
        yield 'no mint key' => [NutzapDleqFailure::NoMintKey, 'no_mint_key'];
        yield 'dleq invalid' => [NutzapDleqFailure::DleqInvalid, 'dleq_invalid'];
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame(
            'A nutzap proof\'s DLEQ proof does not verify against its mint\'s key',
            NutzapDleqFailure::DleqInvalid->message(),
        );
    }

    #[DataProvider('everyCase')]
    public function testEveryCaseHasAMessageApartFromItsCode(NutzapDleqFailure $failure): void
    {
        $this->assertNotSame($failure->value, $failure->message());
    }

    /**
     * @return iterable<string, array{NutzapDleqFailure}>
     */
    public static function everyCase(): iterable
    {
        foreach (NutzapDleqFailure::cases() as $failure) {
            yield $failure->value => [$failure];
        }
    }
}
