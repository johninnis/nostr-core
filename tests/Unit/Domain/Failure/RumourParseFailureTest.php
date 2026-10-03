<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\RumourParseFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RumourParseFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(RumourParseFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{RumourParseFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'malformed' => [RumourParseFailure::Malformed, 'malformed'];
        yield 'id mismatch' => [RumourParseFailure::IdMismatch, 'id_mismatch'];
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame(
            'Stated id does not match the id computed from the rumour fields',
            RumourParseFailure::IdMismatch->message(),
        );
    }

    public function testEveryCaseSeparatesItsCodeFromItsMessage(): void
    {
        foreach (RumourParseFailure::cases() as $failure) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $failure->value);
            $this->assertNotSame('', $failure->message());
            $this->assertNotSame($failure->value, $failure->message());
        }
    }
}
