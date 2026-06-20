<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use FFI;
use Innis\Nostr\Core\Domain\Exception\CryptoException;

final class Nip49Scrypt
{
    private const string CDEF = <<<'C'
        int crypto_pwhash_scryptsalsa208sha256_ll(
            const unsigned char *passwd, unsigned long passwdlen,
            const unsigned char *salt, unsigned long saltlen,
            unsigned long long N, unsigned int r, unsigned int p,
            unsigned char *buf, unsigned long buflen);
        C;

    private const array LIBRARY_NAMES = [
        'libsodium.so.26',
        'libsodium.so.23',
        'libsodium.so',
        'libsodium.26.dylib',
        'libsodium.23.dylib',
        'libsodium.dylib',
    ];

    private const int OUTPUT_LENGTH = 32;
    private const int SCRYPT_BLOCK_SIZE = 8;
    private const int SCRYPT_PARALLELISM = 1;

    private readonly ?FFI $ffi;

    public function __construct()
    {
        $this->ffi = FfiLibraryLoader::tryLoad(self::CDEF, self::LIBRARY_NAMES);
    }

    public function isAvailable(): bool
    {
        return null !== $this->ffi;
    }

    public function derive(string $password, string $salt, int $logN): string
    {
        $ffi = $this->ffi;
        if (null === $ffi) {
            throw new CryptoException('libsodium FFI is not available for NIP-49 scrypt derivation');
        }

        $passwordBuffer = FfiLibraryLoader::toBuffer($ffi, $password);
        $saltBuffer = FfiLibraryLoader::toBuffer($ffi, $salt);
        $output = $ffi->new('unsigned char['.self::OUTPUT_LENGTH.']');

        $returnCode = $ffi->crypto_pwhash_scryptsalsa208sha256_ll(
            $passwordBuffer,
            strlen($password),
            $saltBuffer,
            strlen($salt),
            1 << $logN,
            self::SCRYPT_BLOCK_SIZE,
            self::SCRYPT_PARALLELISM,
            $output,
            self::OUTPUT_LENGTH,
        );

        if (0 !== $returnCode) {
            throw new CryptoException('scrypt derivation returned non-zero status');
        }

        $derived = FFI::string($output, self::OUTPUT_LENGTH);

        FFI::memset($output, 0, self::OUTPUT_LENGTH);
        FFI::memset($passwordBuffer, 0, strlen($password));
        FFI::memset($saltBuffer, 0, strlen($salt));

        return $derived;
    }
}
