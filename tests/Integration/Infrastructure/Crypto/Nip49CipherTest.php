<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\Nip49DecryptionFailedException;
use Innis\Nostr\Core\Domain\Exception\Nip49WorkFactorRefusedException;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip49WorkFactor;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Scrypt;
use Innis\Nostr\Core\Tests\Support\Bech32Mother;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip49CipherTest extends TestCase
{
    private const string SPEC_VECTOR_NCRYPTSEC = 'ncryptsec1qgg9947rlpvqu76pj5ecreduf9jxhselq2nae2kghhvd5g7dgjtcxfqtd67p9m0w57lspw8gsq6yphnm8623nsl8xn9j4jdzz84zm3frztj3z7s35vpzmqf6ksu8r89qk5z2zxfmu5gv8th8wclt0h4p';
    private const string SPEC_VECTOR_NSEC_HEX = '3501454135014541350145413501453fefb02227e449e57cf4d3a3ce05378683';
    private const string SPEC_VECTOR_PASSWORD = 'nostr';

    private Nip49Cipher $adapter;

    protected function setUp(): void
    {
        $this->adapter = Nip49Cipher::create();
    }

    #[Group('ffi')]
    public function testRoundTripDecryptsToSameKey(): void
    {
        $privateKey = PrivateKey::generate();
        $password = static fn (): string => 'correct horse battery staple';

        $ncryptsec = $this->adapter->encrypt($privateKey, $password);
        $decrypted = $this->adapter->decrypt($ncryptsec, $password);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    #[Group('ffi')]
    public function testWrongPasswordThrows(): void
    {
        $privateKey = PrivateKey::generate();
        $ncryptsec = $this->adapter->encrypt($privateKey, static fn (): string => 'correct');

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($ncryptsec, static fn (): string => 'wrong');
    }

    #[Group('ffi')]
    public function testKnownSpecVectorDecrypts(): void
    {
        $ncryptsec = Ncryptsec::tryFromString(self::SPEC_VECTOR_NCRYPTSEC)
            ?? throw new RuntimeException('Spec vector failed HRP/checksum validation');

        $decrypted = $this->adapter->decrypt($ncryptsec, static fn (): string => self::SPEC_VECTOR_PASSWORD);

        $this->assertSame(self::SPEC_VECTOR_NSEC_HEX, $decrypted->toHex());
    }

    #[Group('ffi')]
    public function testDifferentSaltsEachEncryption(): void
    {
        $privateKey = PrivateKey::generate();
        $password = static fn (): string => 'password';

        $first = $this->adapter->encrypt($privateKey, $password);
        $second = $this->adapter->encrypt($privateKey, $password);

        $this->assertNotSame((string) $first, (string) $second);
    }

    #[Group('ffi')]
    public function testNfkcNormalisationMakesEquivalentPasswordsDecrypt(): void
    {
        $privateKey = PrivateKey::generate();
        $nfcPassword = "passw\u{00F6}rd";
        $nfdPassword = "passwo\u{0308}rd";

        $ncryptsec = $this->adapter->encrypt($privateKey, static fn (): string => $nfcPassword);
        $decrypted = $this->adapter->decrypt($ncryptsec, static fn (): string => $nfdPassword);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    #[Group('ffi')]
    public function testKeySecurityByteRoundTripsNotKnownInsecure(): void
    {
        $this->assertKeySecurityRoundTrips(KeySecurityByte::NotKnownInsecure);
    }

    #[Group('ffi')]
    public function testKeySecurityByteRoundTripsKnownInsecure(): void
    {
        $this->assertKeySecurityRoundTrips(KeySecurityByte::KnownInsecure);
    }

    #[Group('ffi')]
    public function testKeySecurityByteRoundTripsUntracked(): void
    {
        $this->assertKeySecurityRoundTrips(KeySecurityByte::Untracked);
    }

    #[Group('ffi')]
    public function testLogNRoundTripsAtFloor(): void
    {
        $this->assertLogNRoundTrips(16);
    }

    #[Group('ffi')]
    public function testLogNRoundTripsAboveFloor(): void
    {
        $this->assertLogNRoundTrips(18);
    }

    #[Group('ffi')]
    public function testEncryptsAtTheWorkFactorFloorByDefault(): void
    {
        $this->assertSame(16, $this->encryptFreshVector()->getLogN());
    }

    #[Group('ffi')]
    public function testDecryptRejectsTamperedCiphertext(): void
    {
        $tampered = $this->flipPayloadByte($this->encryptFreshVector(), Ncryptsec::PAYLOAD_LENGTH - 1);

        $this->expectExceptionObject(new Nip49DecryptionFailedException());
        $this->adapter->decrypt($tampered, static fn (): string => 'pw');
    }

    #[Group('ffi')]
    public function testDecryptRejectsAKeySecurityByteRewrittenToAnotherKnownValue(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), 42, KeySecurityByte::NotKnownInsecure->value);

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($tampered, static fn (): string => 'pw');
    }

    #[Group('ffi')]
    public function testDecryptRefusesLogNAboveMaximumAsAWorkFactorNotAWrongPassword(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), 1, 0xFF);

        $this->expectException(Nip49WorkFactorRefusedException::class);
        $this->expectExceptionMessage('logN 255');
        $this->adapter->decrypt($tampered, static fn (): string => 'pw');
    }

    #[Group('ffi')]
    public function testDecryptRefusesLogNOfZeroAsAWorkFactorNotAWrongPassword(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), 1, 0x00);

        $this->expectException(Nip49WorkFactorRefusedException::class);
        $this->adapter->decrypt($tampered, static fn (): string => 'pw');
    }

    #[Group('ffi')]
    public function testDecryptRefusesLogNAboveConfiguredCeilingAsAWorkFactorNotAWrongPassword(): void
    {
        $password = static fn (): string => 'pw';
        $ncryptsec = Nip49Cipher::create(new Nip49WorkFactor(encryptLogN: 18))->encrypt(PrivateKey::generate(), $password);
        $strict = new Nip49Cipher(Nip49Scrypt::create(), workFactor: new Nip49WorkFactor(maxDecryptLogN: 16));

        $this->expectException(Nip49WorkFactorRefusedException::class);
        $this->expectExceptionMessage('logN 18');
        $strict->decrypt($ncryptsec, $password);
    }

    private function assertKeySecurityRoundTrips(KeySecurityByte $keySecurity): void
    {
        $privateKey = PrivateKey::generate();
        $password = static fn (): string => 'pw';

        $ncryptsec = $this->adapter->encrypt($privateKey, $password, $keySecurity);
        $decrypted = $this->adapter->decrypt($ncryptsec, $password);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    private function assertLogNRoundTrips(int $logN): void
    {
        $privateKey = PrivateKey::generate();
        $password = static fn (): string => 'pw';

        $ncryptsec = Nip49Cipher::create(new Nip49WorkFactor(encryptLogN: $logN))->encrypt($privateKey, $password);
        $decrypted = $this->adapter->decrypt($ncryptsec, $password);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    private function encryptFreshVector(): Ncryptsec
    {
        return $this->adapter->encrypt(PrivateKey::generate(), static fn (): string => 'pw');
    }

    private function tamperPayloadByte(Ncryptsec $source, int $offset, int $value): Ncryptsec
    {
        return $this->rewritePayloadByte($source, $offset, static fn (int $original): int => $value);
    }

    private function flipPayloadByte(Ncryptsec $source, int $offset): Ncryptsec
    {
        return $this->rewritePayloadByte($source, $offset, static fn (int $original): int => $original ^ 0xFF);
    }

    private function rewritePayloadByte(Ncryptsec $source, int $offset, callable $mutate): Ncryptsec
    {
        $decoded = Bech32Codec::decode((string) $source) ?? throw new RuntimeException('Test setup: source did not decode');
        $data = $decoded['data'];
        $data[$offset] = chr($mutate(ord($data[$offset])));
        $bech32 = Bech32Mother::encode(Ncryptsec::HRP, $data);

        return Ncryptsec::tryFromString($bech32)
            ?? throw new RuntimeException('Tampered payload failed Ncryptsec parse - fix test setup');
    }
}
