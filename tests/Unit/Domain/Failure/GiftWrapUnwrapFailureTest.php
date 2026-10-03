<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\GiftWrapUnwrapFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GiftWrapUnwrapFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(GiftWrapUnwrapFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{GiftWrapUnwrapFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'not gift wrap' => [GiftWrapUnwrapFailure::NotGiftWrap, 'not_gift_wrap'];
        yield 'wrap signature invalid' => [GiftWrapUnwrapFailure::WrapSignatureInvalid, 'wrap_signature_invalid'];
        yield 'seal decrypt failed' => [GiftWrapUnwrapFailure::SealDecryptFailed, 'seal_decrypt_failed'];
        yield 'seal malformed' => [GiftWrapUnwrapFailure::SealMalformed, 'seal_malformed'];
        yield 'seal wrong kind' => [GiftWrapUnwrapFailure::SealWrongKind, 'seal_wrong_kind'];
        yield 'seal signature invalid' => [GiftWrapUnwrapFailure::SealSignatureInvalid, 'seal_signature_invalid'];
        yield 'rumour decrypt failed' => [GiftWrapUnwrapFailure::RumourDecryptFailed, 'rumour_decrypt_failed'];
        yield 'rumour malformed' => [GiftWrapUnwrapFailure::RumourMalformed, 'rumour_malformed'];
        yield 'rumour id mismatch' => [GiftWrapUnwrapFailure::RumourIdMismatch, 'rumour_id_mismatch'];
        yield 'rumour signed' => [GiftWrapUnwrapFailure::RumourSigned, 'rumour_signed'];
        yield 'rumour pubkey mismatch' => [GiftWrapUnwrapFailure::RumourPubkeyMismatch, 'rumour_pubkey_mismatch'];
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame(
            'Rumour pubkey does not match the seal pubkey',
            GiftWrapUnwrapFailure::RumourPubkeyMismatch->message(),
        );
    }

    public function testEveryCaseSeparatesItsCodeFromItsMessage(): void
    {
        foreach (GiftWrapUnwrapFailure::cases() as $failure) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $failure->value);
            $this->assertNotSame('', $failure->message());
            $this->assertNotSame($failure->value, $failure->message());
        }
    }
}
