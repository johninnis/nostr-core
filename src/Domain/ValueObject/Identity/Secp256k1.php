<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use FFI;
use RuntimeException;
use Throwable;

final class Secp256k1
{
    private const CDEF = <<<'C'
        typedef struct secp256k1_context_struct secp256k1_context;
        typedef struct { unsigned char data[64]; } secp256k1_xonly_pubkey;
        typedef struct { unsigned char data[96]; } secp256k1_keypair;

        secp256k1_context *secp256k1_context_create(unsigned int flags);
        void secp256k1_context_destroy(secp256k1_context *ctx);
        int secp256k1_context_randomize(secp256k1_context *ctx, const unsigned char *seed32);
        int secp256k1_xonly_pubkey_parse(const secp256k1_context *ctx, secp256k1_xonly_pubkey *pubkey, const unsigned char *input32);
        int secp256k1_xonly_pubkey_serialize(const secp256k1_context *ctx, unsigned char *output32, const secp256k1_xonly_pubkey *pubkey);
        int secp256k1_keypair_create(const secp256k1_context *ctx, secp256k1_keypair *keypair, const unsigned char *seckey32);
        int secp256k1_keypair_xonly_pub(const secp256k1_context *ctx, secp256k1_xonly_pubkey *pubkey, int *pk_parity, const secp256k1_keypair *keypair);
        int secp256k1_schnorrsig_sign32(const secp256k1_context *ctx, unsigned char *sig64, const unsigned char *msg32, const secp256k1_keypair *keypair, const unsigned char *aux_rand32);
        int secp256k1_schnorrsig_verify(const secp256k1_context *ctx, const unsigned char *sig64, const unsigned char *msg, size_t msglen, const secp256k1_xonly_pubkey *pubkey);
        C;

    private const SECP256K1_CONTEXT_SIGN_VERIFY = 769;

    private const LIBRARY_NAMES = [
        'libsecp256k1.so.2',
        'libsecp256k1.so.1',
        'libsecp256k1.so',
        'libsecp256k1.2.dylib',
        'libsecp256k1.dylib',
    ];

    private static bool $initialised = false;
    private static bool $available = false;
    private static ?FFI $ffi = null;
    private static mixed $context = null;

    public static function isAvailable(): bool
    {
        if (!self::$initialised) {
            self::initialise();
        }

        return self::$available;
    }

    public static function verify(string $sigBytes, string $msgBytes, string $pubkeyBytes): bool
    {
        $ffi = self::$ffi;
        $ctx = self::$context;

        $pubkey = $ffi->new('secp256k1_xonly_pubkey');
        if (1 !== $ffi->secp256k1_xonly_pubkey_parse($ctx, FFI::addr($pubkey), self::toBuffer($pubkeyBytes))) {
            return false;
        }

        $msgLen = strlen($msgBytes);

        return 1 === $ffi->secp256k1_schnorrsig_verify($ctx, self::toBuffer($sigBytes), self::toBuffer($msgBytes), $msgLen, FFI::addr($pubkey));
    }

    public static function sign(string $msgBytes, string $privkeyBytes): string
    {
        $ffi = self::$ffi;
        $ctx = self::$context;

        $keypair = $ffi->new('secp256k1_keypair');
        if (1 !== $ffi->secp256k1_keypair_create($ctx, FFI::addr($keypair), self::toBuffer($privkeyBytes))) {
            throw new RuntimeException('Failed to create keypair from private key');
        }

        $sig = $ffi->new('unsigned char[64]');

        if (1 !== $ffi->secp256k1_schnorrsig_sign32($ctx, $sig, self::toBuffer($msgBytes), FFI::addr($keypair), self::toBuffer(random_bytes(32)))) {
            throw new RuntimeException('Schnorr signing failed');
        }

        return FFI::string($sig, 64);
    }

    public static function derivePublicKey(string $privkeyBytes): string
    {
        $ffi = self::$ffi;
        $ctx = self::$context;

        $keypair = $ffi->new('secp256k1_keypair');
        if (1 !== $ffi->secp256k1_keypair_create($ctx, FFI::addr($keypair), self::toBuffer($privkeyBytes))) {
            throw new RuntimeException('Failed to create keypair from private key');
        }

        $pubkey = $ffi->new('secp256k1_xonly_pubkey');
        $ffi->secp256k1_keypair_xonly_pub($ctx, FFI::addr($pubkey), null, FFI::addr($keypair));

        $output = $ffi->new('unsigned char[32]');
        $ffi->secp256k1_xonly_pubkey_serialize($ctx, $output, FFI::addr($pubkey));

        return FFI::string($output, 32);
    }

    public static function reset(): void
    {
        if (null !== self::$context && null !== self::$ffi) {
            self::$ffi->secp256k1_context_destroy(self::$context);
        }

        self::$initialised = false;
        self::$available = false;
        self::$ffi = null;
        self::$context = null;
    }

    private static function initialise(): void
    {
        self::$initialised = true;

        try {
            $ffi = self::loadLibrary();
            if (null === $ffi) {
                self::emitWarning('libsecp256k1 shared library not found. Install with: sudo apt install libsecp256k1-1');

                return;
            }

            self::$ffi = $ffi;

            $ctx = $ffi->secp256k1_context_create(self::SECP256K1_CONTEXT_SIGN_VERIFY);
            $ffi->secp256k1_context_randomize($ctx, self::toBuffer(random_bytes(32)));

            self::$context = $ctx;
            self::$available = true;
        } catch (Throwable $e) {
            self::$ffi = null;
            self::$context = null;
            self::emitWarning('FFI libsecp256k1 unavailable: '.$e->getMessage().'. Falling back to pure PHP (slower)');
        }
    }

    private static function loadLibrary(): ?FFI
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

    private static function toBuffer(string $data): FFI\CData
    {
        $len = strlen($data);
        if (0 === $len) {
            return self::$ffi->new('unsigned char[1]');
        }

        $buf = self::$ffi->new("unsigned char[{$len}]");
        FFI::memcpy($buf, $data, $len);

        return $buf;
    }

    private static function emitWarning(string $message): void
    {
        error_log('[Secp256k1] '.$message);
    }
}
