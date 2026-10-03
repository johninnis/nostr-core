<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface ConversationCipherInterface
{
    public function encrypt(string $plaintext, PrivateKey $ownKey, PublicKey $peer): string;

    public function decrypt(string $ciphertext, PrivateKey $ownKey, PublicKey $peer): string;
}
