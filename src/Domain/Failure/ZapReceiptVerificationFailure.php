<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum ZapReceiptVerificationFailure: string
{
    case WrongKind = 'wrong_kind';
    case ProviderPubkeyMismatch = 'provider_pubkey_mismatch';
    case MissingZapRequest = 'missing_zap_request';
    case MultipleZapRequests = 'multiple_zap_requests';
    case MalformedZapRequest = 'malformed_zap_request';
    case ZapRequestWrongKind = 'zap_request_wrong_kind';
    case MissingInvoiceAmount = 'missing_invoice_amount';
    case AmountMismatch = 'amount_mismatch';
    case LnurlMismatch = 'lnurl_mismatch';
    case ReceiptSignatureInvalid = 'receipt_signature_invalid';
    case ZapRequestSignatureInvalid = 'zap_request_signature_invalid';

    public function message(): string
    {
        return match ($this) {
            self::WrongKind => 'Zap receipt must be kind 9735',
            self::ProviderPubkeyMismatch => 'Zap receipt was not signed by the recipient lnurl provider nostr pubkey',
            self::MissingZapRequest => 'Zap receipt has no description tag carrying the zap request',
            self::MultipleZapRequests => 'Zap receipt carries more than one description tag, so which zap request it attests to is ambiguous',
            self::MalformedZapRequest => 'Zap receipt description tag is not a parsable event',
            self::ZapRequestWrongKind => 'Zap request in the description tag must be kind 9734',
            self::MissingInvoiceAmount => 'Zap receipt bolt11 tag carries no readable amount',
            self::AmountMismatch => 'Zap receipt bolt11 amount does not equal the zap request amount tag',
            self::LnurlMismatch => 'Zap request lnurl tag does not equal the expected recipient lnurl',
            self::ReceiptSignatureInvalid => 'Zap receipt signature is invalid',
            self::ZapRequestSignatureInvalid => 'Zap request signature is invalid, so the claimed sender did not authorise it',
        };
    }
}
