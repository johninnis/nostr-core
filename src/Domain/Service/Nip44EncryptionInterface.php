<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use SensitiveParameter;

interface Nip44EncryptionInterface
{
    public function encrypt(#[SensitiveParameter] string $plaintext, ConversationKey $conversationKey): string;

    public function decrypt(string $payload, ConversationKey $conversationKey): string;
}
