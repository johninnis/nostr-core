<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class ConversationCipher implements ConversationCipherInterface
{
    public function __construct(
        private Nip44EncryptionInterface $encryption,
        private EcdhServiceInterface $ecdh,
    ) {
    }

    #[Override]
    public function encrypt(string $plaintext, PrivateKey $ownKey, PublicKey $peer): string
    {
        $conversationKey = ConversationKey::derive($ownKey, $peer, $this->ecdh);

        try {
            return $this->encryption->encrypt($plaintext, $conversationKey);
        } finally {
            $conversationKey->zero();
        }
    }

    #[Override]
    public function decrypt(string $ciphertext, PrivateKey $ownKey, PublicKey $peer): string
    {
        $conversationKey = ConversationKey::derive($ownKey, $peer, $this->ecdh);

        try {
            return $this->encryption->decrypt($ciphertext, $conversationKey);
        } finally {
            $conversationKey->zero();
        }
    }
}
