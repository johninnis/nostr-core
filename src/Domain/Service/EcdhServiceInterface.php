<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface EcdhServiceInterface
{
    public function computeSharedX(PrivateKey $privateKey, PublicKey $publicKey): string;
}
