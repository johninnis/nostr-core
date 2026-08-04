<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Tests\Fake\QueuedRandomBytesGenerator;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip44EncryptionComplianceTest extends TestCase
{
    private const int PAYLOAD_OVERHEAD = 67;
    private const int MAX_PLAINTEXT_LENGTH = 65535;

    /**
     * These vectors reach the tests through data providers, so an empty corpus currently surfaces as
     * "No tests found in class" rather than a green run. That protection is a side effect of PHPUnit's
     * provider handling, not something this suite states; asserting the corpus makes a truncated
     * vectors file fail for the reason it actually failed.
     *
     * @var array<string, array{string, int}>
     */
    private const array OFFICIAL_VECTOR_COUNTS = [
        'conversation keys' => ['valid.get_conversation_key', 35],
        'message keys' => ['valid.get_message_keys.keys', 32],
        'padded lengths' => ['valid.calc_padded_len', 24],
        'encrypt/decrypt' => ['valid.encrypt_decrypt', 10],
        'long messages' => ['valid.encrypt_decrypt_long_msg', 3],
        'invalid plaintext lengths' => ['invalid.encrypt_msg_lengths', 4],
        'invalid conversation keys' => ['invalid.get_conversation_key', 8],
        'invalid payloads' => ['invalid.decrypt', 12],
    ];

    public function testTheOfficialVectorCorpusIsFullyLoaded(): void
    {
        $vectors = self::loadVectors();

        foreach (self::OFFICIAL_VECTOR_COUNTS as $label => [$path, $expected]) {
            $node = $vectors;
            foreach (explode('.', $path) as $segment) {
                $this->assertIsArray($node);
                $this->assertArrayHasKey($segment, $node, sprintf('Missing %s vectors at %s', $label, $path));
                $node = $node[$segment];
            }

            $this->assertIsArray($node);
            $this->assertCount($expected, $node, sprintf('Expected %d %s vectors', $expected, $label));
        }
    }

    #[DataProvider('conversationKeyVectorsProvider')]
    public function testConversationKeyDerivationFfi(string $sec1, string $pub2, string $expectedKey): void
    {
        $this->assertConversationKey(CryptoFixtures::ecdh(), $sec1, $pub2, $expectedKey);
    }

    #[DataProvider('conversationKeyVectorsProvider')]
    public function testConversationKeyDerivationPurePhp(string $sec1, string $pub2, string $expectedKey): void
    {
        $this->assertConversationKey(new Secp256k1Ecdh(null), $sec1, $pub2, $expectedKey);
    }

    private function assertConversationKey(EcdhServiceInterface $ecdh, string $sec1, string $pub2, string $expectedKey): void
    {
        $privateKey = PrivateKey::tryFromHex($sec1);
        $publicKey = PublicKey::tryFromHex($pub2);

        self::assertNotNull($privateKey);
        self::assertNotNull($publicKey);

        $conversationKey = ConversationKey::derive($privateKey, $publicKey, $ecdh);
        $derivedHex = $conversationKey->expose(static fn (string $bytes): string => bin2hex($bytes));

        self::assertSame($expectedKey, $derivedHex);
    }

    #[DataProvider('encryptDecryptVectorsProvider')]
    public function testEncryptWithKnownVector(
        string $conversationKeyHex,
        string $nonceHex,
        string $plaintext,
        string $expectedPayload,
    ): void {
        $nonce = hex2bin($nonceHex);
        self::assertNotFalse($nonce);

        $adapter = new Nip44Cipher(QueuedRandomBytesGenerator::withBytes($nonce));
        $conversationKey = ConversationKey::tryFromHex($conversationKeyHex);
        self::assertNotNull($conversationKey);

        $encrypted = $adapter->encrypt($plaintext, $conversationKey);

        self::assertSame($expectedPayload, $encrypted);
    }

    #[DataProvider('encryptDecryptVectorsProvider')]
    public function testDecryptWithKnownVector(
        string $conversationKeyHex,
        string $nonceHex,
        string $plaintext,
        string $expectedPayload,
    ): void {
        $adapter = new Nip44Cipher();
        $conversationKey = ConversationKey::tryFromHex($conversationKeyHex);
        self::assertNotNull($conversationKey);

        $decrypted = $adapter->decrypt($expectedPayload, $conversationKey);

        self::assertSame($plaintext, $decrypted);
    }

    #[DataProvider('longMessageVectorsProvider')]
    public function testEncryptDecryptLongMessage(
        string $conversationKeyHex,
        string $nonceHex,
        string $pattern,
        int $repeat,
        string $plaintextSha256,
        string $payloadSha256,
    ): void {
        $plaintext = str_repeat($pattern, $repeat);
        self::assertSame($plaintextSha256, hash('sha256', $plaintext));

        $nonce = hex2bin($nonceHex);
        self::assertNotFalse($nonce);

        $conversationKey = ConversationKey::tryFromHex($conversationKeyHex);
        self::assertNotNull($conversationKey);

        $encrypted = new Nip44Cipher(QueuedRandomBytesGenerator::withBytes($nonce))->encrypt($plaintext, $conversationKey);

        self::assertSame($payloadSha256, hash('sha256', $encrypted));
        self::assertSame($plaintext, new Nip44Cipher()->decrypt($encrypted, $conversationKey));
    }

    #[DataProvider('paddedLengthVectorsProvider')]
    public function testPaddedLengthMatchesSpec(int $unpaddedLength, int $expectedPaddedLength): void
    {
        $adapter = new Nip44Cipher(QueuedRandomBytesGenerator::withBytes(str_repeat("\0", 32)));
        $conversationKey = ConversationKey::tryFromHex(str_repeat('11', 32));
        self::assertNotNull($conversationKey);

        $payload = $adapter->encrypt(str_repeat('a', $unpaddedLength), $conversationKey);

        $decoded = base64_decode($payload, true);
        self::assertNotFalse($decoded);

        self::assertSame($expectedPaddedLength, strlen($decoded) - self::PAYLOAD_OVERHEAD);
    }

    #[DataProvider('invalidPlaintextLengthProvider')]
    public function testRejectsInvalidPlaintextLength(int $length): void
    {
        $conversationKey = ConversationKey::tryFromHex(str_repeat('11', 32));
        self::assertNotNull($conversationKey);

        $this->expectException(EncryptionException::class);

        new Nip44Cipher()->encrypt(str_repeat('a', $length), $conversationKey);
    }

    #[DataProvider('invalidDecryptVectorsProvider')]
    public function testInvalidDecryptionVectors(string $conversationKeyHex, string $payload): void
    {
        $adapter = new Nip44Cipher();
        $conversationKey = ConversationKey::tryFromHex($conversationKeyHex);
        self::assertNotNull($conversationKey);

        $this->expectException(EncryptionException::class);

        $adapter->decrypt($payload, $conversationKey);
    }

    #[DataProvider('invalidConversationKeyVectorsProvider')]
    public function testRejectsInvalidConversationKeyVectors(string $sec1, string $pub2): void
    {
        $privateKey = PrivateKey::tryFromHex($sec1);
        $publicKey = PublicKey::tryFromHex($pub2);

        if (null === $privateKey || null === $publicKey) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->expectException(EcdhException::class);

        ConversationKey::derive($privateKey, $publicKey, new Secp256k1Ecdh(null));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function conversationKeyVectorsProvider(): iterable
    {
        foreach (self::loadVectors()['valid']['get_conversation_key'] as $i => $vector) {
            yield "vector_{$i}" => [$vector['sec1'], $vector['pub2'], $vector['conversation_key']];
        }
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function encryptDecryptVectorsProvider(): iterable
    {
        foreach (self::loadVectors()['valid']['encrypt_decrypt'] as $i => $vector) {
            yield "vector_{$i}" => [
                $vector['conversation_key'],
                $vector['nonce'],
                $vector['plaintext'],
                $vector['payload'],
            ];
        }
    }

    /**
     * @return iterable<string, array{string, string, string, int, string, string}>
     */
    public static function longMessageVectorsProvider(): iterable
    {
        foreach (self::loadVectors()['valid']['encrypt_decrypt_long_msg'] as $i => $vector) {
            yield "long_msg_{$i}" => [
                $vector['conversation_key'],
                $vector['nonce'],
                $vector['pattern'],
                $vector['repeat'],
                $vector['plaintext_sha256'],
                $vector['payload_sha256'],
            ];
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function paddedLengthVectorsProvider(): iterable
    {
        foreach (self::loadVectors()['valid']['calc_padded_len'] as $i => $vector) {
            if ($vector[0] < 1 || $vector[0] > self::MAX_PLAINTEXT_LENGTH) {
                continue;
            }

            yield "padded_len_{$i}" => [$vector[0], $vector[1]];
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPlaintextLengthProvider(): iterable
    {
        foreach (self::loadVectors()['invalid']['encrypt_msg_lengths'] as $i => $length) {
            yield "length_{$i}_{$length}" => [$length];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDecryptVectorsProvider(): iterable
    {
        foreach (self::loadVectors()['invalid']['decrypt'] as $i => $vector) {
            yield "invalid_{$i}_".($vector['note'] ?? 'unknown') => [$vector['conversation_key'], $vector['payload']];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidConversationKeyVectorsProvider(): iterable
    {
        foreach (self::loadVectors()['invalid']['get_conversation_key'] as $i => $vector) {
            yield "invalid_{$i}_".($vector['note'] ?? 'unknown') => [$vector['sec1'], $vector['pub2']];
        }
    }

    /**
     * @return array{
     *     valid: array{
     *         get_conversation_key: list<array{sec1: string, pub2: string, conversation_key: string}>,
     *         encrypt_decrypt: list<array{conversation_key: string, nonce: string, plaintext: string, payload: string}>,
     *         encrypt_decrypt_long_msg: list<array{conversation_key: string, nonce: string, pattern: string, repeat: int, plaintext_sha256: string, payload_sha256: string}>,
     *         calc_padded_len: list<array{int, int}>,
     *     },
     *     invalid: array{
     *         encrypt_msg_lengths: list<int>,
     *         get_conversation_key: list<array{sec1: string, pub2: string, note?: string}>,
     *         decrypt: list<array{conversation_key: string, payload: string, note?: string}>,
     *     },
     * }
     */
    private static function loadVectors(): array
    {
        $content = file_get_contents(__DIR__.'/../Vectors/nip44.vectors.json');
        assert(false !== $content);

        $decoded = json_decode($content, true);
        assert(is_array($decoded));
        assert(is_array($decoded['v2']));

        /**
         * @var array{
         *     valid: array{
         *         get_conversation_key: list<array{sec1: string, pub2: string, conversation_key: string}>,
         *         encrypt_decrypt: list<array{conversation_key: string, nonce: string, plaintext: string, payload: string}>,
         *         encrypt_decrypt_long_msg: list<array{conversation_key: string, nonce: string, pattern: string, repeat: int, plaintext_sha256: string, payload_sha256: string}>,
         *         calc_padded_len: list<array{int, int}>,
         *     },
         *     invalid: array{
         *         encrypt_msg_lengths: list<int>,
         *         get_conversation_key: list<array{sec1: string, pub2: string, note?: string}>,
         *         decrypt: list<array{conversation_key: string, payload: string, note?: string}>,
         *     },
         * } $vectors
         */
        $vectors = $decoded['v2'];

        return $vectors;
    }
}
