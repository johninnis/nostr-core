<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Service\Nip44EncryptionAdapter;
use PHPUnit\Framework\TestCase;

final class Nip44EncryptionAdapterTest extends TestCase
{
    private Nip44EncryptionAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new Nip44EncryptionAdapter();
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $conversationKey = ConversationKey::derive($privateKeyA, $privateKeyB->getPublicKey());

        $plaintext = 'Hello, NIP-44!';
        $encrypted = $this->adapter->encrypt($plaintext, $conversationKey);
        $decrypted = $this->adapter->decrypt($encrypted, $conversationKey);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptDecryptWithSymmetricKeys(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();

        $keyAB = ConversationKey::derive($privateKeyA, $privateKeyB->getPublicKey());
        $keyBA = ConversationKey::derive($privateKeyB, $privateKeyA->getPublicKey());

        $plaintext = 'Symmetric key test';
        $encrypted = $this->adapter->encrypt($plaintext, $keyAB);
        $decrypted = $this->adapter->decrypt($encrypted, $keyBA);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesDifferentCiphertexts(): void
    {
        $conversationKey = $this->createTestKey();

        $encrypted1 = $this->adapter->encrypt('same message', $conversationKey);
        $encrypted2 = $this->adapter->encrypt('same message', $conversationKey);

        self::assertNotSame($encrypted1, $encrypted2);
    }

    public function testEncryptOutputIsValidBase64(): void
    {
        $conversationKey = $this->createTestKey();
        $encrypted = $this->adapter->encrypt('test', $conversationKey);

        $decoded = base64_decode($encrypted, true);
        self::assertNotFalse($decoded);
        self::assertSame($encrypted, base64_encode($decoded));
    }

    public function testEncryptedPayloadStartsWithVersionByte(): void
    {
        $conversationKey = $this->createTestKey();
        $encrypted = $this->adapter->encrypt('test', $conversationKey);

        $decoded = base64_decode($encrypted, true);
        self::assertNotFalse($decoded);
        self::assertSame(2, ord($decoded[0]));
    }

    public function testDecryptRejectsInvalidBase64(): void
    {
        $conversationKey = $this->createTestKey();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid base64 payload');

        $this->adapter->decrypt('not!valid@base64', $conversationKey);
    }

    public function testDecryptRejectsPayloadTooShort(): void
    {
        $conversationKey = $this->createTestKey();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Payload too short');

        $this->adapter->decrypt(base64_encode('short'), $conversationKey);
    }

    public function testDecryptRejectsWrongVersion(): void
    {
        $conversationKey = $this->createTestKey();
        $payload = chr(1).str_repeat("\0", 98);

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unsupported NIP-44 version');

        $this->adapter->decrypt(base64_encode($payload), $conversationKey);
    }

    public function testDecryptRejectsTamperedMac(): void
    {
        $conversationKey = $this->createTestKey();
        $encrypted = $this->adapter->encrypt('test message', $conversationKey);

        $decoded = base64_decode($encrypted, true);
        self::assertNotFalse($decoded);
        $tampered = substr($decoded, 0, -1).chr((ord(substr($decoded, -1)) ^ 0xFF) & 0xFF);

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid MAC');

        $this->adapter->decrypt(base64_encode($tampered), $conversationKey);
    }

    public function testDecryptRejectsWrongKey(): void
    {
        $conversationKey = $this->createTestKey();
        $wrongKey = ConversationKey::fromHex(str_repeat('cd', 32));
        self::assertNotNull($wrongKey);

        $encrypted = $this->adapter->encrypt('secret', $conversationKey);

        $this->expectException(EncryptionException::class);

        $this->adapter->decrypt($encrypted, $wrongKey);
    }

    public function testEncryptRejectsEmptyPlaintext(): void
    {
        $conversationKey = $this->createTestKey();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Plaintext length must be between 1 and 65535 bytes');

        $this->adapter->encrypt('', $conversationKey);
    }

    public function testEncryptDecryptSingleByte(): void
    {
        $conversationKey = $this->createTestKey();

        $decrypted = $this->adapter->decrypt(
            $this->adapter->encrypt('a', $conversationKey),
            $conversationKey
        );

        self::assertSame('a', $decrypted);
    }

    public function testEncryptDecryptLongMessage(): void
    {
        $conversationKey = $this->createTestKey();
        $plaintext = str_repeat('Long message content. ', 500);

        $decrypted = $this->adapter->decrypt(
            $this->adapter->encrypt($plaintext, $conversationKey),
            $conversationKey
        );

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptDecryptUtf8Content(): void
    {
        $conversationKey = $this->createTestKey();
        $plaintext = 'Unicode test content';

        $decrypted = $this->adapter->decrypt(
            $this->adapter->encrypt($plaintext, $conversationKey),
            $conversationKey
        );

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptWithNonceDeterministic(): void
    {
        $conversationKey = $this->createTestKey();
        $nonce = str_repeat("\x01", 32);

        $encrypted1 = $this->adapter->encryptWithNonce('test', $conversationKey, $nonce);
        $encrypted2 = $this->adapter->encryptWithNonce('test', $conversationKey, $nonce);

        self::assertSame($encrypted1, $encrypted2);
    }

    private function createTestKey(): ConversationKey
    {
        $key = ConversationKey::fromHex(str_repeat('ab', 32));
        self::assertNotNull($key);

        return $key;
    }
}
