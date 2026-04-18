<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\Nip44EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use ParagonIE_Sodium_Core_ChaCha20;

final class Nip44EncryptionAdapter implements Nip44EncryptionInterface
{
    private const VERSION = 2;
    private const NONCE_LENGTH = 32;
    private const MAC_LENGTH = 32;
    private const MIN_PLAINTEXT_LENGTH = 1;
    private const MAX_PLAINTEXT_LENGTH = 65535;
    private const MIN_PADDED_LENGTH = 32;

    public function encrypt(string $plaintext, ConversationKey $conversationKey): string
    {
        return $this->encryptWithNonce($plaintext, $conversationKey, random_bytes(self::NONCE_LENGTH));
    }

    public function decrypt(string $payload, ConversationKey $conversationKey): string
    {
        $decoded = base64_decode($payload, true);

        if (false === $decoded) {
            throw new EncryptionException('Invalid base64 payload');
        }

        $decodedLength = strlen($decoded);
        $minLength = 1 + self::NONCE_LENGTH + self::MIN_PADDED_LENGTH + 2 + self::MAC_LENGTH;

        if ($decodedLength < $minLength) {
            throw new EncryptionException('Payload too short');
        }

        $version = ord($decoded[0]);

        if (self::VERSION !== $version) {
            throw new EncryptionException('Unsupported NIP-44 version: '.$version);
        }

        $nonce = substr($decoded, 1, self::NONCE_LENGTH);
        $mac = substr($decoded, -self::MAC_LENGTH);
        $ciphertext = substr($decoded, 1 + self::NONCE_LENGTH, $decodedLength - 1 - self::NONCE_LENGTH - self::MAC_LENGTH);

        $plaintext = $conversationKey->expose(function (string $prk) use ($nonce, $ciphertext, $mac): string {
            $messageKeys = $this->deriveMessageKeys($prk, $nonce);

            $expectedMac = hash_hmac('sha256', $nonce.$ciphertext, $messageKeys['hmacKey'], true);

            if (!hash_equals($expectedMac, $mac)) {
                throw new EncryptionException('Invalid MAC');
            }

            $padded = ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc(
                $ciphertext,
                $messageKeys['chachaNonce'],
                $messageKeys['chachaKey']
            );

            return $this->unpad($padded);
        });
        assert(is_string($plaintext));

        return $plaintext;
    }

    public function encryptWithNonce(string $plaintext, ConversationKey $conversationKey, string $nonce): string
    {
        $plaintextLength = strlen($plaintext);

        if ($plaintextLength < self::MIN_PLAINTEXT_LENGTH || $plaintextLength > self::MAX_PLAINTEXT_LENGTH) {
            throw new EncryptionException('Plaintext length must be between 1 and 65535 bytes');
        }

        if (self::NONCE_LENGTH !== strlen($nonce)) {
            throw new EncryptionException('Nonce must be 32 bytes');
        }

        $payload = $conversationKey->expose(function (string $prk) use ($plaintext, $nonce): string {
            $messageKeys = $this->deriveMessageKeys($prk, $nonce);
            $padded = $this->pad($plaintext);

            $ciphertext = ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc(
                $padded,
                $messageKeys['chachaNonce'],
                $messageKeys['chachaKey']
            );

            $mac = hash_hmac('sha256', $nonce.$ciphertext, $messageKeys['hmacKey'], true);

            return base64_encode(chr(self::VERSION).$nonce.$ciphertext.$mac);
        });
        assert(is_string($payload));

        return $payload;
    }

    private function deriveMessageKeys(string $prk, string $nonce): array
    {
        $expanded = $this->hkdfExpand($prk, $nonce, 76);

        return [
            'chachaKey' => substr($expanded, 0, 32),
            'chachaNonce' => substr($expanded, 32, 12),
            'hmacKey' => substr($expanded, 44, 32),
        ];
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $hashLength = 32;
        $iterations = (int) ceil($length / $hashLength);
        $output = '';
        $previous = '';

        for ($i = 1; $i <= $iterations; ++$i) {
            $previous = hash_hmac('sha256', $previous.$info.chr($i & 0xFF), $prk, true);
            $output .= $previous;
        }

        return substr($output, 0, $length);
    }

    private function pad(string $plaintext): string
    {
        $plaintextLength = strlen($plaintext);
        $paddedLength = $this->calculatePaddedLength($plaintextLength);
        $lengthPrefix = pack('n', $plaintextLength);

        return $lengthPrefix.$plaintext.str_repeat("\0", $paddedLength - $plaintextLength);
    }

    private function unpad(string $padded): string
    {
        $unpacked = unpack('n', substr($padded, 0, 2));
        if (false === $unpacked) {
            throw new EncryptionException('Invalid padding');
        }
        $plaintextLength = $unpacked[1];

        if ($plaintextLength < self::MIN_PLAINTEXT_LENGTH
            || $plaintextLength > self::MAX_PLAINTEXT_LENGTH
            || $plaintextLength + 2 > strlen($padded)) {
            throw new EncryptionException('Invalid padding');
        }

        $expectedTotalLength = $this->calculatePaddedLength($plaintextLength) + 2;

        if ($expectedTotalLength !== strlen($padded)) {
            throw new EncryptionException('Invalid padding length');
        }

        $plaintext = substr($padded, 2, $plaintextLength);
        $zeroPadding = substr($padded, 2 + $plaintextLength);

        if ('' !== $zeroPadding && $zeroPadding !== str_repeat("\0", strlen($zeroPadding))) {
            throw new EncryptionException('Non-zero padding bytes');
        }

        return $plaintext;
    }

    private function calculatePaddedLength(int $unpaddedLength): int
    {
        if ($unpaddedLength <= self::MIN_PADDED_LENGTH) {
            return self::MIN_PADDED_LENGTH;
        }

        $nextPower = 1 << ((int) floor(log($unpaddedLength - 1, 2)) + 1);
        $chunk = $nextPower <= 256 ? self::MIN_PADDED_LENGTH : (int) ($nextPower / 8);

        return $chunk * ((int) floor(($unpaddedLength - 1) / $chunk) + 1);
    }
}
