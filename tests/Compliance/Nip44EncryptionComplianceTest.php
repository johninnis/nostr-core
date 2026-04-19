<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Service\Nip44EncryptionAdapter;
use Innis\Nostr\Core\Tests\Fixtures\QueuedRandomBytesGenerator;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class Nip44EncryptionComplianceTest extends TestCase
{
    use WithCryptoServices;

    #[DataProvider('conversationKeyVectorsProvider')]
    public function testConversationKeyDerivation(string $sec1, string $pub2, string $expectedKey): void
    {
        $privateKey = PrivateKey::fromHex($sec1);
        $publicKey = PublicKey::fromHex($pub2);

        self::assertNotNull($privateKey);
        self::assertNotNull($publicKey);

        $conversationKey = ConversationKey::derive($privateKey, $publicKey, $this->ecdhService());
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

        $adapter = new Nip44EncryptionAdapter(QueuedRandomBytesGenerator::withBytes($nonce));
        $conversationKey = ConversationKey::fromHex($conversationKeyHex);
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
        $adapter = new Nip44EncryptionAdapter();
        $conversationKey = ConversationKey::fromHex($conversationKeyHex);
        self::assertNotNull($conversationKey);

        $decrypted = $adapter->decrypt($expectedPayload, $conversationKey);

        self::assertSame($plaintext, $decrypted);
    }

    #[DataProvider('paddedLengthVectorsProvider')]
    public function testCalculatePaddedLength(int $unpaddedLength, int $expectedPaddedLength): void
    {
        $adapter = new Nip44EncryptionAdapter();
        $reflection = new ReflectionMethod($adapter, 'calculatePaddedLength');

        $result = $reflection->invoke($adapter, $unpaddedLength);

        self::assertSame($expectedPaddedLength, $result);
    }

    #[DataProvider('invalidDecryptVectorsProvider')]
    public function testInvalidDecryptionVectors(string $conversationKeyHex, string $payload): void
    {
        $adapter = new Nip44EncryptionAdapter();
        $conversationKey = ConversationKey::fromHex($conversationKeyHex);
        self::assertNotNull($conversationKey);

        $this->expectException(EncryptionException::class);

        $adapter->decrypt($payload, $conversationKey);
    }

    public static function conversationKeyVectorsProvider(): iterable
    {
        $vectors = self::loadVectors()['valid']['get_conversation_key'];

        foreach ($vectors as $i => $vector) {
            yield "vector_{$i}" => [
                $vector['sec1'],
                $vector['pub2'],
                $vector['conversation_key'],
            ];
        }
    }

    public static function encryptDecryptVectorsProvider(): iterable
    {
        $vectors = self::loadVectors()['valid']['encrypt_decrypt'];

        foreach ($vectors as $i => $vector) {
            yield "vector_{$i}" => [
                $vector['conversation_key'],
                $vector['nonce'],
                $vector['plaintext'],
                $vector['payload'],
            ];
        }
    }

    public static function paddedLengthVectorsProvider(): iterable
    {
        $vectors = self::loadVectors()['valid']['calc_padded_len'];

        foreach ($vectors as $i => $vector) {
            yield "padded_len_{$i}" => [
                $vector[0],
                $vector[1],
            ];
        }
    }

    public static function invalidDecryptVectorsProvider(): iterable
    {
        $vectors = self::loadVectors()['invalid']['decrypt'];

        foreach ($vectors as $i => $vector) {
            yield "invalid_{$i}_".($vector['note'] ?? 'unknown') => [
                $vector['conversation_key'],
                $vector['payload'],
            ];
        }
    }

    private static function loadVectors(): array
    {
        $content = file_get_contents(__DIR__.'/../Fixtures/nip44.vectors.json');
        assert(false !== $content);

        $decoded = json_decode($content, true);
        assert(is_array($decoded));
        assert(is_array($decoded['v2']));

        return $decoded['v2'];
    }
}
