<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\Exception\Nip49DecryptionFailedException;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Service\Nip49EncryptionAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip49EncryptionAdapterTest extends TestCase
{
    private const FAST_LOG_N = 1;
    private const SPEC_VECTOR_NCRYPTSEC = 'ncryptsec1qgg9947rlpvqu76pj5ecreduf9jxhselq2nae2kghhvd5g7dgjtcxfqtd67p9m0w57lspw8gsq6yphnm8623nsl8xn9j4jdzz84zm3frztj3z7s35vpzmqf6ksu8r89qk5z2zxfmu5gv8th8wclt0h4p';
    private const SPEC_VECTOR_NSEC_HEX = '3501454135014541350145413501453fefb02227e449e57cf4d3a3ce05378683';
    private const SPEC_VECTOR_PASSWORD = 'nostr';

    private Nip49EncryptionAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new Nip49EncryptionAdapter();
    }

    public function testRoundTripDecryptsToSameKey(): void
    {
        $privateKey = PrivateKey::generate();
        $password = 'correct horse battery staple';

        $ncryptsec = $this->adapter->encrypt($privateKey, $password, self::FAST_LOG_N);
        $decrypted = $this->adapter->decrypt($ncryptsec, $password);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    public function testWrongPasswordThrows(): void
    {
        $privateKey = PrivateKey::generate();
        $ncryptsec = $this->adapter->encrypt($privateKey, 'correct', self::FAST_LOG_N);

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($ncryptsec, 'wrong');
    }

    public function testKnownSpecVectorDecrypts(): void
    {
        $ncryptsec = Ncryptsec::fromString(self::SPEC_VECTOR_NCRYPTSEC)
            ?? throw new RuntimeException('Spec vector failed HRP/checksum validation');

        $decrypted = $this->adapter->decrypt($ncryptsec, self::SPEC_VECTOR_PASSWORD);

        $this->assertSame(self::SPEC_VECTOR_NSEC_HEX, $decrypted->toHex());
    }

    public function testDifferentSaltsEachEncryption(): void
    {
        $privateKey = PrivateKey::generate();
        $password = 'password';

        $first = $this->adapter->encrypt($privateKey, $password, self::FAST_LOG_N);
        $second = $this->adapter->encrypt($privateKey, $password, self::FAST_LOG_N);

        $this->assertNotSame((string) $first, (string) $second);
    }

    public function testNfkcNormalisationMakesEquivalentPasswordsDecrypt(): void
    {
        $privateKey = PrivateKey::generate();
        $nfcPassword = "passw\u{00F6}rd";
        $nfdPassword = "passwo\u{0308}rd";

        $ncryptsec = $this->adapter->encrypt($privateKey, $nfcPassword, self::FAST_LOG_N);
        $decrypted = $this->adapter->decrypt($ncryptsec, $nfdPassword);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    public function testKeySecurityByteRoundTripsClientSideOnly(): void
    {
        $this->assertKeySecurityRoundTrips(KeySecurityByte::ClientSideOnly);
    }

    public function testKeySecurityByteRoundTripsUsableUntrusted(): void
    {
        $this->assertKeySecurityRoundTrips(KeySecurityByte::UsableUntrusted);
    }

    public function testKeySecurityByteRoundTripsUnknown(): void
    {
        $this->assertKeySecurityRoundTrips(KeySecurityByte::Unknown);
    }

    public function testLogNRoundTripsLow(): void
    {
        $this->assertLogNRoundTrips(1);
    }

    public function testLogNRoundTripsMedium(): void
    {
        $this->assertLogNRoundTrips(4);
    }

    public function testEncryptRejectsLogNBelowMinimum(): void
    {
        $privateKey = PrivateKey::generate();

        $this->expectException(InvalidArgumentException::class);
        $this->adapter->encrypt($privateKey, 'p', 0);
    }

    public function testEncryptRejectsLogNAboveMaximum(): void
    {
        $privateKey = PrivateKey::generate();

        $this->expectException(InvalidArgumentException::class);
        $this->adapter->encrypt($privateKey, 'p', 64);
    }

    public function testDecryptRejectsTamperedCiphertext(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), Ncryptsec::PAYLOAD_LENGTH - 1, 0xFF);

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($tampered, 'pw');
    }

    public function testDecryptRejectsUnknownKeySecurityByte(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), 42, 0xFF);

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($tampered, 'pw');
    }

    public function testDecryptRejectsLogNAboveMaximum(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), 1, 0xFF);

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($tampered, 'pw');
    }

    public function testDecryptRejectsLogNOfZero(): void
    {
        $tampered = $this->tamperPayloadByte($this->encryptFreshVector(), 1, 0x00);

        $this->expectException(Nip49DecryptionFailedException::class);
        $this->adapter->decrypt($tampered, 'pw');
    }

    private function assertKeySecurityRoundTrips(KeySecurityByte $keySecurity): void
    {
        $privateKey = PrivateKey::generate();
        $password = 'pw';

        $ncryptsec = $this->adapter->encrypt($privateKey, $password, self::FAST_LOG_N, $keySecurity);
        $decrypted = $this->adapter->decrypt($ncryptsec, $password);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    private function assertLogNRoundTrips(int $logN): void
    {
        $privateKey = PrivateKey::generate();
        $password = 'pw';

        $ncryptsec = $this->adapter->encrypt($privateKey, $password, $logN);
        $decrypted = $this->adapter->decrypt($ncryptsec, $password);

        $this->assertSame($privateKey->toHex(), $decrypted->toHex());
    }

    private function encryptFreshVector(): Ncryptsec
    {
        return $this->adapter->encrypt(PrivateKey::generate(), 'pw', self::FAST_LOG_N);
    }

    private function tamperPayloadByte(Ncryptsec $source, int $offset, int $value): Ncryptsec
    {
        $decoded = Bech32Codec::decode((string) $source);
        $data = $decoded['data'];
        $data[$offset] = $value;
        $bech32 = Bech32Codec::encode(Ncryptsec::HRP, $data);

        return Ncryptsec::fromString($bech32)
            ?? throw new RuntimeException('Tampered payload failed Ncryptsec parse - fix test setup');
    }
}
