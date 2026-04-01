<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;

interface Nip44EncryptionInterface
{
    public function encrypt(string $plaintext, ConversationKey $conversationKey): string;

    public function encryptWithNonce(string $plaintext, ConversationKey $conversationKey, string $nonce): string;

    public function decrypt(string $payload, ConversationKey $conversationKey): string;
}
