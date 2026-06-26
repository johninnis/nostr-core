<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum Nip05VerificationFailure: string
{
    case FetchFailed = 'fetch_failed';
    case MissingNames = 'missing_names';
    case NameNotFound = 'name_not_found';
    case PubkeyMismatch = 'pubkey_mismatch';

    public function message(): string
    {
        return match ($this) {
            self::FetchFailed => 'Failed to fetch the NIP-05 well-known document',
            self::MissingNames => 'NIP-05 document is missing the names object',
            self::NameNotFound => 'Name not found in the NIP-05 document',
            self::PubkeyMismatch => 'Pubkey in the NIP-05 document does not match the expected pubkey',
        };
    }
}
