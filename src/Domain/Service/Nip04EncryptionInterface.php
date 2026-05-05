<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;

interface Nip04EncryptionInterface
{
    public function encrypt(string $plaintext, SecretKeyMaterial $sharedSecret): string;

    public function decrypt(string $payload, SecretKeyMaterial $sharedSecret): string;
}
