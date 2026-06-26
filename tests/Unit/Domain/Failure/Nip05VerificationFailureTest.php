<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailure;
use PHPUnit\Framework\TestCase;

final class Nip05VerificationFailureTest extends TestCase
{
    public function testValueIsAStableMachineCode(): void
    {
        $this->assertSame('pubkey_mismatch', Nip05VerificationFailure::PubkeyMismatch->value);
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
