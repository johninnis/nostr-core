<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Exception;

final class Nip49WorkFactorRefusedException extends NostrException
{
    public function __construct(int $logN)
    {
        parent::__construct(sprintf('NIP-49 decryption refused an ncryptsec of logN %d, outside the work factor this cipher decrypts', $logN));
    }
}
