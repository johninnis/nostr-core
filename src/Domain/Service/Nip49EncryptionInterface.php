<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Closure;
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;

interface Nip49EncryptionInterface
{
    /**
     * @param Closure(): string $passwordProvider
     */
    public function encrypt(
        PrivateKey $privateKey,
        Closure $passwordProvider,
        KeySecurityByte $keySecurity = KeySecurityByte::Untracked,
    ): Ncryptsec;

    /**
     * @param Closure(): string $passwordProvider
     */
    public function decrypt(Ncryptsec $ncryptsec, Closure $passwordProvider): PrivateKey;
}
