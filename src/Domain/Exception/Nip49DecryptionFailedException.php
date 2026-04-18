<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Exception;

final class Nip49DecryptionFailedException extends NostrException
{
    public function __construct()
    {
        parent::__construct('NIP-49 decryption failed');
    }
}
