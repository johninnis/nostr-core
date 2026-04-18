<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;

interface Nip49EncryptionInterface
{
    public function encrypt(
        PrivateKey $privateKey,
        string $password,
        int $logN = 16,
        KeySecurityByte $keySecurity = KeySecurityByte::Unknown,
    ): Ncryptsec;

    public function decrypt(Ncryptsec $ncryptsec, string $password): PrivateKey;
}
