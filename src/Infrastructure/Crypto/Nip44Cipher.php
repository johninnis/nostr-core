<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\Base64Codec;
use Innis\Nostr\Core\Domain\Service\Nip44EncryptionInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use InvalidArgumentException;
use Override;
use ParagonIE_Sodium_Core_ChaCha20;

final readonly class Nip44Cipher implements Nip44EncryptionInterface
{
    public const int DEFAULT_MAX_PLAINTEXT_LENGTH = 262144;

    private const int VERSION = 2;
    private const int NONCE_LENGTH = 32;
    private const int MAC_LENGTH = 32;
    private const int MIN_PLAINTEXT_LENGTH = 1;
    private const int NIP_MAX_PLAINTEXT_LENGTH = 4294967295;
    private const int EXTENDED_PREFIX_THRESHOLD = 65536;
    private const int SHORT_PREFIX_LENGTH = 2;
    private const int EXTENDED_PREFIX_LENGTH = 6;
    private const int MIN_PAYLOAD_LENGTH = 132;
    private const string NON_BASE64_FLAG = '#';
    private const string NOT_UTF8 = 'Plaintext is not valid UTF-8';
    private const int MIN_PADDED_LENGTH = 32;
    private const int SHA256_LENGTH = 32;
    private const int CHACHA_KEY_LENGTH = 32;
    private const int CHACHA_NONCE_LENGTH = 12;
    private const int HMAC_KEY_LENGTH = 32;
    private const int MESSAGE_KEYS_LENGTH = self::CHACHA_KEY_LENGTH + self::CHACHA_NONCE_LENGTH + self::HMAC_KEY_LENGTH;
    private const int MIN_DECODED_LENGTH = 1 + self::NONCE_LENGTH + self::SHORT_PREFIX_LENGTH + self::MIN_PADDED_LENGTH + self::MAC_LENGTH;

    private int $maxPayloadLength;

    public function __construct(
        private RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator(),
        private int $maxPlaintextLength = self::DEFAULT_MAX_PLAINTEXT_LENGTH,
    ) {
        if ($maxPlaintextLength < self::MIN_PLAINTEXT_LENGTH || $maxPlaintextLength > self::NIP_MAX_PLAINTEXT_LENGTH) {
            throw new InvalidArgumentException(sprintf('Maximum plaintext length must be between 1 and %d bytes, got %d', self::NIP_MAX_PLAINTEXT_LENGTH, $maxPlaintextLength));
        }

        $this->maxPayloadLength = $this->payloadLengthFor($maxPlaintextLength);
    }

    #[Override]
    public function encrypt(string $plaintext, ConversationKey $conversationKey): string
    {
        return $this->encryptWithNonce($plaintext, $conversationKey, $this->randomBytes->bytes(self::NONCE_LENGTH));
    }

    #[Override]
    public function decrypt(string $payload, ConversationKey $conversationKey): string
    {
        $payloadLength = strlen($payload);

        // Deliberate: the ceiling is the base64 length of the payload that carries the configured maximum plaintext, checked before any character is read — see ADR-0117
        if ($payloadLength > $this->maxPayloadLength) {
            throw new EncryptionException('Payload size out of bounds');
        }

        // Deliberate: NIP-44 requires a `#` payload to be reported as an unsupported version, whatever its length within the ceiling — see ADR-0117
        if (str_starts_with($payload, self::NON_BASE64_FLAG)) {
            throw new EncryptionException('Unsupported NIP-44 version: non-base64 encoding');
        }

        if ($payloadLength < self::MIN_PAYLOAD_LENGTH) {
            throw new EncryptionException('Payload size out of bounds');
        }

        $decoded = Base64Codec::tryDecodeCanonical($payload);

        if (null === $decoded) {
            throw new EncryptionException('Invalid base64 payload');
        }

        $decodedLength = strlen($decoded);

        if ($decodedLength < self::MIN_DECODED_LENGTH) {
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

            try {
                $expectedMac = hash_hmac('sha256', $nonce.$ciphertext, $messageKeys['hmacKey'], true);

                if (!hash_equals($expectedMac, $mac)) {
                    throw new EncryptionException('Invalid MAC');
                }

                // Deliberate: ext-sodium exposes no raw chacha20-ietf stream; sodium_compat's internal class is the only pure-PHP route — see ADR-0037
                $padded = ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc(
                    $ciphertext,
                    $messageKeys['chachaNonce'],
                    $messageKeys['chachaKey']
                );

                try {
                    return $this->unpad($padded);
                } finally {
                    sodium_memzero($padded);
                }
            } finally {
                sodium_memzero($messageKeys['chachaKey']);
                sodium_memzero($messageKeys['chachaNonce']);
                sodium_memzero($messageKeys['hmacKey']);
            }
        });

        // Deliberate: NIP-44 decodes the unpadded bytes as UTF-8, so a plaintext that is not UTF-8 is refused and a byte order mark is kept — see ADR-0116
        if (!mb_check_encoding($plaintext, 'UTF-8')) {
            sodium_memzero($plaintext);

            throw new EncryptionException(self::NOT_UTF8);
        }

        return $plaintext;
    }

    // Deliberate: kept private, not public, so production code cannot supply a reusable nonce — see ADR-0014
    private function encryptWithNonce(string $plaintext, ConversationKey $conversationKey, string $nonce): string
    {
        $plaintextLength = strlen($plaintext);

        if ($plaintextLength < self::MIN_PLAINTEXT_LENGTH || $plaintextLength > $this->maxPlaintextLength) {
            throw new EncryptionException(sprintf('Plaintext length must be between 1 and %d bytes', $this->maxPlaintextLength));
        }

        // Deliberate: NIP-44 encodes the content from UTF-8, and decrypt refuses any other plaintext, so encrypt never seals one — see ADR-0116
        if (!mb_check_encoding($plaintext, 'UTF-8')) {
            throw new EncryptionException(self::NOT_UTF8);
        }

        if (self::NONCE_LENGTH !== strlen($nonce)) {
            throw new EncryptionException('Nonce must be 32 bytes');
        }

        $payload = $conversationKey->expose(function (string $prk) use ($plaintext, $nonce): string {
            $messageKeys = $this->deriveMessageKeys($prk, $nonce);
            $padded = $this->pad($plaintext);

            try {
                // Deliberate: ext-sodium exposes no raw chacha20-ietf stream; sodium_compat's internal class is the only pure-PHP route — see ADR-0037
                $ciphertext = ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc(
                    $padded,
                    $messageKeys['chachaNonce'],
                    $messageKeys['chachaKey']
                );

                $mac = hash_hmac('sha256', $nonce.$ciphertext, $messageKeys['hmacKey'], true);

                return base64_encode(chr(self::VERSION).$nonce.$ciphertext.$mac);
            } finally {
                sodium_memzero($padded);
                sodium_memzero($messageKeys['chachaKey']);
                sodium_memzero($messageKeys['chachaNonce']);
                sodium_memzero($messageKeys['hmacKey']);
            }
        });

        return $payload;
    }

    /**
     * @return array{chachaKey: string, chachaNonce: string, hmacKey: string}
     */
    private function deriveMessageKeys(string $prk, string $nonce): array
    {
        $expanded = $this->hkdfExpand($prk, $nonce, self::MESSAGE_KEYS_LENGTH);

        try {
            return [
                'chachaKey' => substr($expanded, 0, self::CHACHA_KEY_LENGTH),
                'chachaNonce' => substr($expanded, self::CHACHA_KEY_LENGTH, self::CHACHA_NONCE_LENGTH),
                'hmacKey' => substr($expanded, self::CHACHA_KEY_LENGTH + self::CHACHA_NONCE_LENGTH, self::HMAC_KEY_LENGTH),
            ];
        } finally {
            sodium_memzero($expanded);
        }
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $iterations = (int) ceil($length / self::SHA256_LENGTH);
        $output = '';
        $previous = '';

        for ($i = 1; $i <= $iterations; ++$i) {
            $previous = hash_hmac('sha256', $previous.$info.chr($i & 0xFF), $prk, true);
            $output .= $previous;
        }

        try {
            return substr($output, 0, $length);
        } finally {
            sodium_memzero($output);
            sodium_memzero($previous);
        }
    }

    private function pad(string $plaintext): string
    {
        $plaintextLength = strlen($plaintext);
        $paddedLength = $this->calculatePaddedLength($plaintextLength);
        $lengthPrefix = $plaintextLength >= self::EXTENDED_PREFIX_THRESHOLD
            ? "\0\0".pack('N', $plaintextLength)
            : pack('n', $plaintextLength);

        return $lengthPrefix.$plaintext.str_repeat("\0", $paddedLength - $plaintextLength);
    }

    private function unpad(string $padded): string
    {
        $paddedLength = strlen($padded);
        [$plaintextLength, $prefixLength] = $this->readLengthPrefix($padded);

        if ($plaintextLength < self::MIN_PLAINTEXT_LENGTH
            || $plaintextLength + $prefixLength > $paddedLength) {
            throw new EncryptionException('Invalid padding');
        }

        if ($plaintextLength > $this->maxPlaintextLength) {
            throw new EncryptionException('Plaintext exceeds the maximum length');
        }

        if ($this->calculatePaddedLength($plaintextLength) + $prefixLength !== $paddedLength) {
            throw new EncryptionException('Invalid padding length');
        }

        $plaintext = substr($padded, $prefixLength, $plaintextLength);
        $zeroPadding = substr($padded, $prefixLength + $plaintextLength);

        if ('' !== $zeroPadding && !hash_equals(str_repeat("\0", strlen($zeroPadding)), $zeroPadding)) {
            throw new EncryptionException('Non-zero padding bytes');
        }

        return $plaintext;
    }

    /**
     * @return array{int, int}
     */
    private function readLengthPrefix(string $padded): array
    {
        $short = unpack('n', $padded);

        if (false === $short) {
            throw new EncryptionException('Invalid padding');
        }

        if (0 !== $short[1]) {
            return [$short[1], self::SHORT_PREFIX_LENGTH];
        }

        $extended = strlen($padded) >= self::EXTENDED_PREFIX_LENGTH
            ? unpack('N', $padded, self::SHORT_PREFIX_LENGTH)
            : false;

        // Deliberate: a u16 of zero announces the six-byte prefix, and a length below 65536 in it is one the two-byte prefix carries, so it is refused rather than read as a second spelling — see ADR-0117
        if (false === $extended || $extended[1] < self::EXTENDED_PREFIX_THRESHOLD) {
            throw new EncryptionException('Invalid padding');
        }

        return [$extended[1], self::EXTENDED_PREFIX_LENGTH];
    }

    private function payloadLengthFor(int $plaintextLength): int
    {
        $prefixLength = $plaintextLength >= self::EXTENDED_PREFIX_THRESHOLD ? self::EXTENDED_PREFIX_LENGTH : self::SHORT_PREFIX_LENGTH;
        $rawLength = 1 + self::NONCE_LENGTH + $prefixLength + $this->calculatePaddedLength($plaintextLength) + self::MAC_LENGTH;

        return 4 * intdiv($rawLength + 2, 3);
    }

    private function calculatePaddedLength(int $unpaddedLength): int
    {
        if ($unpaddedLength <= self::MIN_PADDED_LENGTH) {
            return self::MIN_PADDED_LENGTH;
        }

        $nextPower = 1 << strlen(decbin($unpaddedLength - 1));
        $chunk = $nextPower <= 256 ? self::MIN_PADDED_LENGTH : intdiv($nextPower, 8);

        return $chunk * (intdiv($unpaddedLength - 1, $chunk) + 1);
    }
}
