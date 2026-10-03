<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum ZapReceiptVerificationFailure: string
{
    case ProviderPubkeyMismatch = 'provider_pubkey_mismatch';
    case LnurlMismatch = 'lnurl_mismatch';
    case ReceiptSignatureInvalid = 'receipt_signature_invalid';
    case ZapRequestSignatureInvalid = 'zap_request_signature_invalid';

    public function message(): string
    {
        return match ($this) {
            self::ProviderPubkeyMismatch => 'Zap receipt was not signed by the recipient lnurl provider nostr pubkey',
            self::LnurlMismatch => 'Zap request lnurl tag does not equal the expected recipient lnurl',
            self::ReceiptSignatureInvalid => 'Zap receipt signature is invalid',
            self::ZapRequestSignatureInvalid => 'Zap request signature is invalid, so the claimed sender did not authorise it',
        };
    }
}
