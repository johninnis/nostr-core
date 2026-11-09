<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip04Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Scrypt;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SecretsStayOutOfTracesTest extends TestCase
{
    private const string SECRET = "\x11\x22\x33\x44\x55\x66\x77\x11\x22\x33\x44\x55\x66\x77\x11\x22\x33\x44\x55\x66\x77\x11\x22\x33\x44\x55\x66\x77\x11\x22\x33\x44";
    private const string OFF_CURVE_KEY_HEX = '0000000000000000000000000000000000000000000000000000000000000005';

    private string|false $priorIgnoreArgs = false;

    protected function setUp(): void
    {
        $this->priorIgnoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', (string) $this->priorIgnoreArgs);
    }

    public function testATamperedNip44PayloadLeavesTheConversationKeyOutOfTheTrace(): void
    {
        $cipher = new Nip44Cipher();
        $key = ConversationKey::tryFromBytes(self::SECRET) ?? self::fail('The fixture is no conversation key');
        $payload = base64_decode($cipher->encrypt('hello', $key), true) ?: self::fail('The payload is no base64');
        $tampered = substr($payload, 0, -1).($payload[-1] ^ "\x01");

        $this->assertSame([], $this->framesHolding(self::SECRET, EncryptionException::class, static fn () => $cipher->decrypt(base64_encode($tampered), $key)));
    }

    #[IgnoreDeprecations]
    public function testATamperedNip04PayloadLeavesTheSharedSecretOutOfTheTrace(): void
    {
        $sharedSecret = SecretKeyMaterial::fromBytes(self::SECRET);
        $payload = base64_encode(str_repeat("\xAB", 32)).'?iv='.base64_encode(str_repeat("\xCD", 16));

        $this->assertSame([], $this->framesHolding(self::SECRET, EncryptionException::class, static fn () => new Nip04Cipher()->decrypt($payload, $sharedSecret)));
    }

    public function testARefusedNip44PlaintextIsLeftOutOfTheTrace(): void
    {
        $key = ConversationKey::tryFromBytes(self::SECRET) ?? self::fail('The fixture is no conversation key');
        $plaintext = "secret \xFF plaintext";

        $this->assertSame([], $this->framesHolding($plaintext, EncryptionException::class, static fn () => new Nip44Cipher()->encrypt($plaintext, $key)));
    }

    #[IgnoreDeprecations]
    public function testARefusedNip04PlaintextIsLeftOutOfTheTrace(): void
    {
        $sharedSecret = SecretKeyMaterial::fromBytes(self::SECRET);
        $plaintext = "secret \xFF plaintext";

        $this->assertSame([], $this->framesHolding($plaintext, EncryptionException::class, static fn () => new Nip04Cipher()->encrypt($plaintext, $sharedSecret)));
    }

    public function testAKeyAgreementOnThePurePhpPathLeavesThePrivateKeyOutOfTheTrace(): void
    {
        $this->assertNoPrivateKeyInTheTraceOfAgreeingWith(Secp256k1Ecdh::createPurePhp());
    }

    #[Group('ffi')]
    public function testAKeyAgreementOnTheNativePathLeavesThePrivateKeyOutOfTheTrace(): void
    {
        $this->assertNoPrivateKeyInTheTraceOfAgreeingWith(Secp256k1Ecdh::create());
    }

    #[Group('ffi')]
    public function testAFailedScryptLeavesThePasswordOutOfTheTrace(): void
    {
        $password = 'a password nobody should log';

        $this->assertSame([], $this->framesHolding($password, CryptoException::class, static fn () => Nip49Scrypt::create()->derive($password, str_repeat("\0", 16), 0)));
    }

    private function assertNoPrivateKeyInTheTraceOfAgreeingWith(Secp256k1Ecdh $ecdh): void
    {
        $privateKey = PrivateKey::tryFromBytes(self::SECRET) ?? self::fail('The fixture is no private key');
        $offCurve = PublicKey::tryFromHex(self::OFF_CURVE_KEY_HEX) ?? self::fail('The fixture is no public key');

        $this->assertSame([], $this->framesHolding(self::SECRET, EcdhException::class, static fn () => $ecdh->computeSharedX($privateKey, $offCurve)));
    }

    /**
     * @param class-string<Throwable> $expected
     * @param callable(): mixed       $refused
     *
     * @return list<string>
     */
    private function framesHolding(string $secret, string $expected, callable $refused): array
    {
        try {
            $refused();
        } catch (Throwable $thrown) {
            self::assertInstanceOf($expected, $thrown);

            return array_values(array_map(
                static fn (array $frame): string => $frame['function'],
                array_filter($thrown->getTrace(), static fn (array $frame): bool => self::class !== ($frame['class'] ?? null) && in_array($secret, $frame['args'] ?? [], true)),
            ));
        }

        self::fail('Expected the call to be refused');
    }
}
