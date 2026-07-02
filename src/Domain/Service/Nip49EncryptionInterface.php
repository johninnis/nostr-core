<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Closure;
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;

interface Nip49EncryptionInterface
{
    // Deliberate: the key, the password source and the two independently-defaulted ncryptsec parameters (KDF cost and key-security byte) are distinct inputs, not a cohesive group to fold into a parameter object
    public function encrypt(
        PrivateKey $privateKey,
        Closure $passwordProvider,
        int $logN = 16,
        KeySecurityByte $keySecurity = KeySecurityByte::Unknown,
    ): Ncryptsec;

    public function decrypt(Ncryptsec $ncryptsec, Closure $passwordProvider): PrivateKey;
}
