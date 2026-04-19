<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;

interface SignatureServiceInterface
{
    public function sign(PrivateKey $privateKey, string $message): Signature;

    public function verify(PublicKey $publicKey, string $message, Signature $signature): bool;

    public function derivePublicKey(PrivateKey $privateKey): PublicKey;
}
