<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Deprecated;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;

interface Nip04EncryptionInterface
{
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use Nip44EncryptionInterface instead')]
    public function encrypt(string $plaintext, SecretKeyMaterial $sharedSecret): string;

    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use Nip44EncryptionInterface instead')]
    public function decrypt(string $payload, SecretKeyMaterial $sharedSecret): string;
}
