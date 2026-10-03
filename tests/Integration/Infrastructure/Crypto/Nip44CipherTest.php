<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Tests\Fake\QueuedRandomBytesGenerator;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use InvalidArgumentException;
use ParagonIE_Sodium_Core_ChaCha20;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip44CipherTest extends TestCase
{
    private Nip44Cipher $adapter;

    protected function setUp(): void
    {
        $this->adapter = new Nip44Cipher();
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyB = CryptoFixtures::signer()->derivePublicKey($privateKeyB);
        $conversationKey = ConversationKey::derive($privateKeyA, $publicKeyB, CryptoFixtures::ecdh());

        $plaintext = 'Hello, NIP-44!';
        $encrypted = $this->adapter->encrypt($plaintext, $conversationKey);
        $decrypted = $this->adapter->decrypt($encrypted, $conversationKey);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptDecryptWithSymmetricKeys(): void
    {
        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyA = CryptoFixtures::signer()->derivePublicKey($privateKeyA);
        $publicKeyB = CryptoFixtures::signer()->derivePublicKey($privateKeyB);

        $keyAB = ConversationKey::derive($privateKeyA, $publicKeyB, CryptoFixtures::ecdh());
        $keyBA = ConversationKey::derive($privateKeyB, $publicKeyA, CryptoFixtures::ecdh());

        $plaintext = 'Symmetric key test';
        $encrypted = $this->adapter->encrypt($plaintext, $keyAB);
        $decrypted = $this->adapter->decrypt($encrypted, $keyBA);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptRefusesPlaintextThatIsNotUtf8(): void
    {
        $this->expectExceptionObject(new EncryptionException('Plaintext is not valid UTF-8'));

        $this->adapter->encrypt("caf\xC3", $this->createTestKey());
    }

    public function testDecryptRefusesPlaintextThatIsNotUtf8(): void
    {
        $payload = $this->sealPadded(pack('n', 4)."caf\xC3".str_repeat("\0", 28), $this->createTestKey());

        $this->expectExceptionObject(new EncryptionException('Plaintext is not valid UTF-8'));

        $this->adapter->decrypt($payload, $this->createTestKey());
    }

    public function testDecryptKeepsAByteOrderMark(): void
    {
        $plaintext = "\u{FEFF}hello";
        $payload = $this->adapter->encrypt($plaintext, $this->createTestKey());

        self::assertSame($plaintext, $this->adapter->decrypt($payload, $this->createTestKey()));
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

        $this->adapter->decrypt(str_repeat('!', 132), $conversationKey);
    }

    #[DataProvider('nonCanonicalBase64')]
    public function testDecryptRejectsAPayloadThatIsNotCanonicalBase64(callable $alter): void
    {
        $conversationKey = $this->createTestKey();
        $payload = $this->adapter->encrypt(str_repeat('a', 40), $conversationKey);

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid base64 payload');

        $this->adapter->decrypt($alter($payload), $conversationKey);
    }

    /**
     * @return iterable<string, array{callable(string): string}>
     */
    public static function nonCanonicalBase64(): iterable
    {
        yield 'padding left off' => [static fn (string $encoded): string => rtrim($encoded, '=')];
        yield 'non-zero trailing bits' => [self::withNonZeroTrailingBits(...)];
        yield 'whitespace inside' => [static fn (string $encoded): string => substr($encoded, 0, 4).' '.substr($encoded, 4)];
    }

    public function testDecryptRejectsPayloadTooShort(): void
    {
        $conversationKey = $this->createTestKey();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Payload size out of bounds');

        $this->adapter->decrypt(base64_encode('short'), $conversationKey);
    }

    public function testDecryptReportsAHashPrefixedPayloadAsAnUnsupportedVersion(): void
    {
        $this->expectExceptionObject(new EncryptionException('Unsupported NIP-44 version: non-base64 encoding'));

        $this->adapter->decrypt('#'.str_repeat('A', 131), $this->createTestKey());
    }

    public function testDecryptReportsAHashPrefixedPayloadShorterThanTheMinimumAsAnUnsupportedVersion(): void
    {
        $this->expectExceptionObject(new EncryptionException('Unsupported NIP-44 version: non-base64 encoding'));

        $this->adapter->decrypt('#abc', $this->createTestKey());
    }

    public function testDecryptRefusesAHashPrefixedPayloadOverTheCeilingByLength(): void
    {
        $this->expectExceptionObject(new EncryptionException('Payload size out of bounds'));

        $this->adapter->decrypt('#'.str_repeat('A', 349620), $this->createTestKey());
    }

    public function testEncryptAcceptsAPlaintextAtTheDefaultMaximum(): void
    {
        $plaintext = str_repeat('a', 262144);

        self::assertSame($plaintext, $this->adapter->decrypt($this->adapter->encrypt($plaintext, $this->createTestKey()), $this->createTestKey()));
    }

    public function testEncryptRefusesAPlaintextJustOverTheDefaultMaximum(): void
    {
        $this->expectExceptionObject(new EncryptionException('Plaintext length must be between 1 and 262144 bytes'));

        $this->adapter->encrypt(str_repeat('a', 262145), $this->createTestKey());
    }

    public function testThePayloadAtTheDefaultMaximumIsTheDerivedCeiling(): void
    {
        self::assertSame(349620, strlen($this->adapter->encrypt(str_repeat('a', 262144), $this->createTestKey())));
    }

    public function testDecryptDecodesAPayloadAtTheDefaultCeiling(): void
    {
        $this->expectExceptionObject(new EncryptionException('Invalid base64 payload'));

        $this->adapter->decrypt(str_repeat('!', 349620), $this->createTestKey());
    }

    public function testDecryptRefusesAPayloadJustOverTheDefaultCeilingBeforeDecodingIt(): void
    {
        $this->expectExceptionObject(new EncryptionException('Payload size out of bounds'));

        $this->adapter->decrypt(str_repeat('!', 349621), $this->createTestKey());
    }

    public function testTheDefaultCeilingRefusesAPayloadSealedUnderARaisedOne(): void
    {
        $payload = new Nip44Cipher(maxPlaintextLength: 1048576)->encrypt(str_repeat('a', 262145), $this->createTestKey());

        $this->expectExceptionObject(new EncryptionException('Payload size out of bounds'));

        $this->adapter->decrypt($payload, $this->createTestKey());
    }

    public function testARaisedCeilingOpensAPlaintextOfSeventyThousandBytes(): void
    {
        $cipher = new Nip44Cipher(maxPlaintextLength: 1048576);
        $plaintext = str_repeat('a', 70000);

        self::assertSame($plaintext, $cipher->decrypt($cipher->encrypt($plaintext, $this->createTestKey()), $this->createTestKey()));
    }

    public function testALoweredCeilingRefusesAPlaintextOverItThatSharesItsPaddedLength(): void
    {
        $payload = $this->adapter->encrypt(str_repeat('a', 1010), $this->createTestKey());

        $this->expectExceptionObject(new EncryptionException('Plaintext exceeds the maximum length'));

        new Nip44Cipher(maxPlaintextLength: 1000)->decrypt($payload, $this->createTestKey());
    }

    #[DataProvider('maximumsOutsideTheNip')]
    public function testTheMaximumMustBeOneTheNipAllows(int $maxPlaintextLength): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip44Cipher(maxPlaintextLength: $maxPlaintextLength);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function maximumsOutsideTheNip(): iterable
    {
        yield 'zero' => [0];
        yield 'above 4294967295' => [4294967296];
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
        $wrongKey = ConversationKey::tryFromHex(str_repeat('cd', 32));
        self::assertNotNull($wrongKey);

        $encrypted = $this->adapter->encrypt('secret', $conversationKey);

        $this->expectException(EncryptionException::class);

        $this->adapter->decrypt($encrypted, $wrongKey);
    }

    public function testEncryptRejectsEmptyPlaintext(): void
    {
        $conversationKey = $this->createTestKey();

        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Plaintext length must be between 1 and 262144 bytes');

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

    public function testEncryptIsDeterministicUnderFixedNonce(): void
    {
        $conversationKey = $this->createTestKey();
        $nonce = str_repeat("\x01", 32);

        $firstAdapter = new Nip44Cipher(QueuedRandomBytesGenerator::withBytes($nonce));
        $secondAdapter = new Nip44Cipher(QueuedRandomBytesGenerator::withBytes($nonce));

        $encrypted1 = $firstAdapter->encrypt('test', $conversationKey);
        $encrypted2 = $secondAdapter->encrypt('test', $conversationKey);

        self::assertSame($encrypted1, $encrypted2);
    }

    #[DataProvider('paddingBoundaryLengthsProvider')]
    public function testEncryptDecryptRoundTripAtPaddingBoundary(int $length): void
    {
        $conversationKey = $this->createTestKey();
        $plaintext = str_repeat('x', $length);

        $decrypted = $this->adapter->decrypt(
            $this->adapter->encrypt($plaintext, $conversationKey),
            $conversationKey,
        );

        self::assertSame($plaintext, $decrypted);
        self::assertSame($length, strlen($decrypted));
    }

    public function testAPlaintextOfSixtyFiveThousandFiveHundredAndThirtySixBytesTakesTheSixBytePrefix(): void
    {
        $decoded = base64_decode($this->adapter->encrypt(str_repeat('x', 65536), $this->createTestKey()), true);
        self::assertNotFalse($decoded);

        self::assertSame(65607, strlen($decoded));
    }

    public function testDecryptReadsAnExtendedPrefixSealedByHand(): void
    {
        $plaintext = str_repeat('a', 65536);
        $payload = $this->sealPadded("\0\0".pack('N', 65536).$plaintext, $this->createTestKey());

        self::assertSame($plaintext, $this->adapter->decrypt($payload, $this->createTestKey()));
    }

    public function testDecryptRefusesAnAuthenticPayloadWhosePaddingIsNotAllZero(): void
    {
        $payload = $this->sealPadded(pack('n', 5).'hello'.str_repeat("\0", 26)."\x07", $this->createTestKey());

        $this->expectExceptionObject(new EncryptionException('Non-zero padding bytes'));

        $this->adapter->decrypt($payload, $this->createTestKey());
    }

    #[DataProvider('invalidExtendedPrefixes')]
    public function testDecryptRefusesAnExtendedPrefixThatIsNotAValidLength(string $padded, string $message): void
    {
        $payload = $this->sealPadded($padded, $this->createTestKey());

        $this->expectExceptionObject(new EncryptionException($message));

        $this->adapter->decrypt($payload, $this->createTestKey());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidExtendedPrefixes(): iterable
    {
        yield 'a length the u16 prefix could carry' => ["\0\0".pack('N', 65535).str_repeat('a', 65535)."\0", 'Invalid padding'];
        yield 'a length of zero' => ["\0\0".pack('N', 0).str_repeat("\0", 32), 'Invalid padding'];
        yield 'a length longer than the padded plaintext' => ["\0\0".pack('N', 70000).str_repeat('a', 65536), 'Invalid padding'];
        yield 'padding short of the next bucket' => ["\0\0".pack('N', 65537).str_repeat('a', 65537).str_repeat("\0", 100), 'Invalid padding length'];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function paddingBoundaryLengthsProvider(): iterable
    {
        yield 'minimum_padded_minus_one' => [31];
        yield 'minimum_padded_exact' => [32];
        yield 'minimum_padded_plus_one' => [33];
        yield 'chunk_threshold_minus_one' => [255];
        yield 'chunk_threshold_exact' => [256];
        yield 'chunk_threshold_plus_one' => [257];
        yield 'power_of_two_minus_one' => [511];
        yield 'power_of_two_exact' => [512];
        yield 'power_of_two_plus_one' => [513];
        yield 'u16_prefix_maximum_minus_one' => [65534];
        yield 'u16_prefix_maximum' => [65535];
        yield 'extended_prefix_threshold' => [65536];
        yield 'extended_prefix_threshold_plus_one' => [65537];
    }

    private function sealPadded(string $padded, ConversationKey $conversationKey): string
    {
        $nonce = str_repeat("\x02", 32);

        return $conversationKey->expose(static function (string $prk) use ($padded, $nonce): string {
            $first = hash_hmac('sha256', $nonce."\x01", $prk, true);
            $second = hash_hmac('sha256', $first.$nonce."\x02", $prk, true);
            $third = hash_hmac('sha256', $second.$nonce."\x03", $prk, true);
            $keys = $first.$second.$third;

            $ciphertext = ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc($padded, substr($keys, 32, 12), substr($keys, 0, 32));
            $mac = hash_hmac('sha256', $nonce.$ciphertext, substr($keys, 44, 32), true);

            return base64_encode("\x02".$nonce.$ciphertext.$mac);
        });
    }

    private function createTestKey(): ConversationKey
    {
        $key = ConversationKey::tryFromHex(str_repeat('ab', 32));
        self::assertNotNull($key);

        return $key;
    }

    private static function withNonZeroTrailingBits(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
        $padded = strlen($encoded) - strlen(rtrim($encoded, '='));
        $last = strlen($encoded) - $padded - 1;

        return substr($encoded, 0, $last).$alphabet[(int) strpos($alphabet, $encoded[$last]) + 1].str_repeat('=', $padded);
    }
}
