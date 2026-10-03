<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum GiftWrapUnwrapFailure: string
{
    case NotGiftWrap = 'not_gift_wrap';
    case WrapSignatureInvalid = 'wrap_signature_invalid';
    case SealDecryptFailed = 'seal_decrypt_failed';
    case SealMalformed = 'seal_malformed';
    case SealWrongKind = 'seal_wrong_kind';
    case SealSignatureInvalid = 'seal_signature_invalid';
    case RumourDecryptFailed = 'rumour_decrypt_failed';
    case RumourMalformed = 'rumour_malformed';
    case RumourIdMismatch = 'rumour_id_mismatch';
    case RumourSigned = 'rumour_signed';
    case RumourPubkeyMismatch = 'rumour_pubkey_mismatch';

    public function message(): string
    {
        return match ($this) {
            self::NotGiftWrap => 'Event must be kind 1059 or 21059 (gift wrap)',
            self::WrapSignatureInvalid => 'Gift wrap signature is invalid',
            self::SealDecryptFailed => 'Failed to decrypt gift wrap',
            self::SealMalformed => 'Decrypted gift wrap is not an event whose only tags are expirations with a valid timestamp',
            self::SealWrongKind => 'Decrypted event is not a seal (kind 13)',
            self::SealSignatureInvalid => 'Seal signature is invalid',
            self::RumourDecryptFailed => 'Failed to decrypt seal',
            self::RumourMalformed => 'Decrypted seal is not an unsigned event',
            self::RumourIdMismatch => 'Rumour id does not match the id computed from its fields',
            self::RumourSigned => 'Decrypted rumour must not be signed',
            self::RumourPubkeyMismatch => 'Rumour pubkey does not match the seal pubkey',
        };
    }
}
