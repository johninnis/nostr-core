<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Infrastructure\Adapter\Nip44EncryptionAdapter;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use PHPUnit\Framework\TestCase;

final class Nip44PropertyComplianceTest extends TestCase
{
    use WithCryptoServices;

    private const ITERATIONS = 200;
    private const MIN_PLAINTEXT_LENGTH = 1;
    private const MAX_PLAINTEXT_LENGTH = 65535;

    public function testEncryptDecryptRoundTripAcrossRandomLengthsAndKeys(): void
    {
        $adapter = new Nip44EncryptionAdapter();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $plaintextLength = random_int(self::MIN_PLAINTEXT_LENGTH, self::MAX_PLAINTEXT_LENGTH);
            $plaintext = random_bytes($plaintextLength);
            $conversationKey = ConversationKey::fromBytes(random_bytes(32));
            $this->assertNotNull($conversationKey);

            $encrypted = $adapter->encrypt($plaintext, $conversationKey);
            $decrypted = $adapter->decrypt($encrypted, $conversationKey);

            $this->assertSame(
                $plaintext,
                $decrypted,
                sprintf('iteration %d (len %d): round-trip plaintext mismatch', $i, $plaintextLength),
            );
        }
    }

    public function testSingleBitTamperingInCiphertextTriggersMacFailure(): void
    {
        $adapter = new Nip44EncryptionAdapter();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $plaintextLength = random_int(self::MIN_PLAINTEXT_LENGTH, 256);
            $plaintext = random_bytes($plaintextLength);
            $conversationKey = ConversationKey::fromBytes(random_bytes(32));
            $this->assertNotNull($conversationKey);

            $encrypted = $adapter->encrypt($plaintext, $conversationKey);
            $tampered = $this->flipOneBitInPayload($encrypted);

            try {
                $adapter->decrypt($tampered, $conversationKey);
                $this->fail(sprintf(
                    'iteration %d (len %d): tampered ciphertext decrypted without throwing',
                    $i,
                    $plaintextLength,
                ));
            } catch (EncryptionException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSingleBitTamperingInMacTriggersMacFailure(): void
    {
        $adapter = new Nip44EncryptionAdapter();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $plaintext = random_bytes(random_int(1, 256));
            $conversationKey = ConversationKey::fromBytes(random_bytes(32));
            $this->assertNotNull($conversationKey);

            $encrypted = $adapter->encrypt($plaintext, $conversationKey);

            $decoded = base64_decode($encrypted, true);
            $this->assertNotFalse($decoded);

            $tamperedMac = substr($decoded, 0, -1).chr((ord($decoded[strlen($decoded) - 1]) ^ 0x01) & 0xFF);
            $tamperedPayload = base64_encode($tamperedMac);

            $this->expectsMacFailure($adapter, $tamperedPayload, $conversationKey, $i);
        }
    }

    public function testDecryptFailsAcrossRandomConversationKeys(): void
    {
        $adapter = new Nip44EncryptionAdapter();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $plaintext = random_bytes(random_int(1, 256));
            $correctKey = ConversationKey::fromBytes(random_bytes(32));
            $wrongKey = ConversationKey::fromBytes(random_bytes(32));
            $this->assertNotNull($correctKey);
            $this->assertNotNull($wrongKey);

            $encrypted = $adapter->encrypt($plaintext, $correctKey);

            try {
                $adapter->decrypt($encrypted, $wrongKey);
                $this->fail(sprintf('iteration %d: decryption under wrong key did not throw', $i));
            } catch (EncryptionException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function flipOneBitInPayload(string $base64Payload): string
    {
        $decoded = base64_decode($base64Payload, true);
        $this->assertNotFalse($decoded);

        $length = strlen($decoded);
        $this->assertGreaterThan(1, $length);
        assert($length > 1);

        $offset = random_int(1, $length - 1);
        $byte = ord($decoded[$offset]);
        $bitMask = 1 << random_int(0, 7);

        $decoded[$offset] = chr(($byte ^ $bitMask) & 0xFF);

        return base64_encode($decoded);
    }

    private function expectsMacFailure(
        Nip44EncryptionAdapter $adapter,
        string $payload,
        ConversationKey $key,
        int $iteration,
    ): void {
        try {
            $adapter->decrypt($payload, $key);
            $this->fail(sprintf('iteration %d: MAC-tampered payload decrypted without throwing', $iteration));
        } catch (EncryptionException) {
            $this->addToAssertionCount(1);
        }
    }
}
