<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;

final class Nip04InputValidator
{
    public const string DECRYPTION_FAILED = 'NIP-04 decryption failed';

    private const int MAX_PLAINTEXT_LENGTH = 65567;
    private const int MIN_PAYLOAD_LENGTH = 52;
    private const int MAX_PAYLOAD_LENGTH = 87472;

    private function __construct()
    {
    }

    public static function validatePlaintext(string $plaintext): void
    {
        // Deliberate: decrypt refuses a plaintext that is not UTF-8, so encrypt never seals one — see ADR-0116
        if (!mb_check_encoding($plaintext, 'UTF-8')) {
            throw new EncryptionException('NIP-04 plaintext must be UTF-8');
        }

        // Deliberate: the largest plaintext whose payload fits the decrypt ceiling, so this cipher never writes a payload it would refuse to open — see ADR-0118
        if (strlen($plaintext) > self::MAX_PLAINTEXT_LENGTH) {
            throw new EncryptionException(sprintf('NIP-04 plaintext must be at most %d bytes', self::MAX_PLAINTEXT_LENGTH));
        }
    }

    public static function validatePayloadLength(string $payload): void
    {
        $payloadLength = strlen($payload);

        if ($payloadLength < self::MIN_PAYLOAD_LENGTH || $payloadLength > self::MAX_PAYLOAD_LENGTH) {
            throw new EncryptionException(self::DECRYPTION_FAILED);
        }
    }
}
