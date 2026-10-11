<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Deprecated;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class Nip04ConversationCipher implements ConversationCipherInterface
{
    private const string ENCRYPTION_FAILED = 'NIP-04 encryption failed';

    public function __construct(
        private Nip04EncryptionInterface $encryption,
        private EcdhServiceInterface $ecdh,
    ) {
    }

    #[Override]
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use ConversationCipher instead')]
    public function encrypt(string $plaintext, PrivateKey $ownKey, PublicKey $peer): string
    {
        Nip04InputValidator::validatePlaintext($plaintext);

        try {
            $sharedSecret = $this->ecdh->computeSharedX($ownKey, $peer);
        } catch (EcdhException $refusal) {
            throw new EncryptionException(self::ENCRYPTION_FAILED, previous: $refusal);
        }

        try {
            return $this->encryption->encrypt($plaintext, $sharedSecret);
        } finally {
            $sharedSecret->zero();
        }
    }

    // Deliberate: the payload's length is judged before the key agreement, and a sender key it refuses is one more payload this cipher cannot decrypt, reported with NIP-04's one failure and no cause — see ADR-0139
    #[Override]
    #[Deprecated(message: 'NIP-04 is unauthenticated and deprecated by the Nostr protocol; use ConversationCipher instead')]
    public function decrypt(string $ciphertext, PrivateKey $ownKey, PublicKey $peer): string
    {
        Nip04InputValidator::validatePayloadLength($ciphertext);

        try {
            $sharedSecret = $this->ecdh->computeSharedX($ownKey, $peer);
        } catch (EcdhException) {
            throw new EncryptionException(Nip04InputValidator::DECRYPTION_FAILED);
        }

        try {
            return $this->encryption->decrypt($ciphertext, $sharedSecret);
        } finally {
            $sharedSecret->zero();
        }
    }
}
