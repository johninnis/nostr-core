<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use PHPUnit\Framework\TestCase;

final class Nip98ValidationFailureTest extends TestCase
{
    public function testValueIsAStableMachineCode(): void
    {
        $this->assertSame('wrong_kind', Nip98ValidationFailure::WrongKind->value);
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame('Event must be kind 27235', Nip98ValidationFailure::WrongKind->message());
    }

    public function testEveryCaseSeparatesItsCodeFromItsMessage(): void
    {
        foreach (Nip98ValidationFailure::cases() as $failure) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $failure->value);
            $this->assertNotSame('', $failure->message());
            $this->assertNotSame($failure->value, $failure->message());
        }
    }
}
