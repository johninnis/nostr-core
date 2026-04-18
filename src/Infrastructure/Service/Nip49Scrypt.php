<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use FFI;
use RuntimeException;
use Throwable;

final class Nip49Scrypt
{
    private const CDEF = <<<'C'
        int crypto_pwhash_scryptsalsa208sha256_ll(
            const unsigned char *passwd, unsigned long passwdlen,
            const unsigned char *salt, unsigned long saltlen,
            unsigned long long N, unsigned int r, unsigned int p,
            unsigned char *buf, unsigned long buflen);
        C;

    private const LIBRARY_NAMES = [
        'libsodium.so.26',
        'libsodium.so.23',
        'libsodium.so',
        'libsodium.26.dylib',
        'libsodium.23.dylib',
        'libsodium.dylib',
    ];

    private const OUTPUT_LENGTH = 32;
    private const SCRYPT_BLOCK_SIZE = 8;
    private const SCRYPT_PARALLELISM = 1;

    private bool $initialised = false;
    private bool $available = false;
    private ?FFI $ffi = null;

    public function isAvailable(): bool
    {
        if (!$this->initialised) {
            $this->initialise();
        }

        return $this->available;
    }

    public function derive(string $password, string $salt, int $logN): string
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('libsodium FFI is not available for NIP-49 scrypt derivation');
        }

        $ffi = $this->ffi;
        assert(null !== $ffi);

        $passwordBuffer = $this->toBuffer($ffi, $password);
        $saltBuffer = $this->toBuffer($ffi, $salt);
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
            throw new RuntimeException('scrypt derivation returned non-zero status');
        }

        $derived = FFI::string($output, self::OUTPUT_LENGTH);

        FFI::memset($output, 0, self::OUTPUT_LENGTH);
        FFI::memset($passwordBuffer, 0, strlen($password));
        FFI::memset($saltBuffer, 0, strlen($salt));

        return $derived;
    }

    public function reset(): void
    {
        $this->initialised = false;
        $this->available = false;
        $this->ffi = null;
    }

    private function initialise(): void
    {
        $this->initialised = true;

        try {
            $ffi = $this->loadLibrary();
            if (null === $ffi) {
                return;
            }
            $this->ffi = $ffi;
            $this->available = true;
        } catch (Throwable) {
            $this->ffi = null;
        }
    }

    private function loadLibrary(): ?FFI
    {
        foreach (self::LIBRARY_NAMES as $name) {
            try {
                return FFI::cdef(self::CDEF, $name);
            } catch (FFI\Exception) {
                continue;
            }
        }

        return null;
    }

    private function toBuffer(FFI $ffi, string $data): FFI\CData
    {
        $length = strlen($data);
        $buffer = $ffi->new("unsigned char[{$length}]");
        FFI::memcpy($buffer, $data, $length);

        return $buffer;
    }
}
