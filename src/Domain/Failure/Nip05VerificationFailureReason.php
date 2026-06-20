<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum Nip05VerificationFailureReason: string
{
    case FetchFailed = 'fetch_failed';
    case MissingNames = 'missing_names';
    case NameNotFound = 'name_not_found';
    case PubkeyMismatch = 'pubkey_mismatch';
}
