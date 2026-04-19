<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use FFI;
use LogicException;
use RuntimeException;
use Throwable;

final class LibSecp256k1Ffi
{
    private const CDEF = <<<'C'
        typedef struct secp256k1_context_struct secp256k1_context;
        typedef struct { unsigned char data[64]; } secp256k1_xonly_pubkey;
        typedef struct { unsigned char data[64]; } secp256k1_pubkey;
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
        int secp256k1_ec_pubkey_parse(const secp256k1_context *ctx, secp256k1_pubkey *pubkey, const unsigned char *input, size_t inputlen);
        int secp256k1_ec_pubkey_tweak_mul(const secp256k1_context *ctx, secp256k1_pubkey *pubkey, const unsigned char *tweak32);
        int secp256k1_ec_pubkey_serialize(const secp256k1_context *ctx, unsigned char *output, size_t *outputlen, const secp256k1_pubkey *pubkey, unsigned int flags);
        C;

    private const CONTEXT_SIGN_VERIFY = 769;
    private const EC_COMPRESSED_FLAG = 258;
    private const COMPRESSED_PUBKEY_LENGTH = 33;
    private const XONLY_PUBKEY_LENGTH = 32;

    private const LIBRARY_NAMES = [
        'libsecp256k1.so.2',
        'libsecp256k1.so.1',
        'libsecp256k1.so',
        'libsecp256k1.2.dylib',
        'libsecp256k1.dylib',
    ];

    private function __construct(
        private readonly FFI $ffi,
        private readonly mixed $context,
    ) {
    }

    public static function tryLoad(string $seed32): ?self
    {
        $ffi = FfiLibraryLoader::tryLoad(self::CDEF, self::LIBRARY_NAMES);
        if (null === $ffi) {
            return null;
        }

        try {
            $context = $ffi->secp256k1_context_create(self::CONTEXT_SIGN_VERIFY);
            $ffi->secp256k1_context_randomize($context, FfiLibraryLoader::toBuffer($ffi, $seed32));
        } catch (Throwable) {
            return null;
        }

        return new self($ffi, $context);
    }

    public function sign(string $messageBytes, string $privkeyBytes, string $auxRand32): string
    {
        $keypair = $this->ffi->new('secp256k1_keypair');
        if (1 !== $this->ffi->secp256k1_keypair_create($this->context, FFI::addr($keypair), FfiLibraryLoader::toBuffer($this->ffi, $privkeyBytes))) {
            throw new RuntimeException('Failed to create keypair from private key');
        }

        $sig = $this->ffi->new('unsigned char[64]');

        if (1 !== $this->ffi->secp256k1_schnorrsig_sign32($this->context, $sig, FfiLibraryLoader::toBuffer($this->ffi, $messageBytes), FFI::addr($keypair), FfiLibraryLoader::toBuffer($this->ffi, $auxRand32))) {
            throw new RuntimeException('Schnorr signing failed');
        }

        return FFI::string($sig, 64);
    }

    public function verify(string $sigBytes, string $messageBytes, string $pubkeyBytes): bool
    {
        $pubkey = $this->ffi->new('secp256k1_xonly_pubkey');
        if (1 !== $this->ffi->secp256k1_xonly_pubkey_parse($this->context, FFI::addr($pubkey), FfiLibraryLoader::toBuffer($this->ffi, $pubkeyBytes))) {
            return false;
        }

        return 1 === $this->ffi->secp256k1_schnorrsig_verify(
            $this->context,
            FfiLibraryLoader::toBuffer($this->ffi, $sigBytes),
            FfiLibraryLoader::toBuffer($this->ffi, $messageBytes),
            strlen($messageBytes),
            FFI::addr($pubkey),
        );
    }

    public function derivePublicKey(string $privkeyBytes): string
    {
        $keypair = $this->ffi->new('secp256k1_keypair');
        if (1 !== $this->ffi->secp256k1_keypair_create($this->context, FFI::addr($keypair), FfiLibraryLoader::toBuffer($this->ffi, $privkeyBytes))) {
            throw new RuntimeException('Failed to create keypair from private key');
        }

        $pubkey = $this->ffi->new('secp256k1_xonly_pubkey');
        $this->ffi->secp256k1_keypair_xonly_pub($this->context, FFI::addr($pubkey), null, FFI::addr($keypair));

        $output = $this->ffi->new('unsigned char[32]');
        $this->ffi->secp256k1_xonly_pubkey_serialize($this->context, $output, FFI::addr($pubkey));

        return FFI::string($output, 32);
    }

    public function computeSharedX(string $privkeyBytes, string $peerXOnlyPubkeyBytes): string
    {
        $compressed = "\x02".$peerXOnlyPubkeyBytes;

        $pubkey = $this->ffi->new('secp256k1_pubkey');
        if (1 !== $this->ffi->secp256k1_ec_pubkey_parse(
            $this->context,
            FFI::addr($pubkey),
            FfiLibraryLoader::toBuffer($this->ffi, $compressed),
            self::COMPRESSED_PUBKEY_LENGTH,
        )) {
            throw new LogicException('ECDH public key is not a valid curve point');
        }

        if (1 !== $this->ffi->secp256k1_ec_pubkey_tweak_mul(
            $this->context,
            FFI::addr($pubkey),
            FfiLibraryLoader::toBuffer($this->ffi, $privkeyBytes),
        )) {
            throw new LogicException('ECDH shared point is the identity');
        }

        $output = $this->ffi->new('unsigned char['.self::COMPRESSED_PUBKEY_LENGTH.']');
        $outputLen = $this->ffi->new('size_t');
        $outputLen->cdata = self::COMPRESSED_PUBKEY_LENGTH;

        if (1 !== $this->ffi->secp256k1_ec_pubkey_serialize(
            $this->context,
            $output,
            FFI::addr($outputLen),
            FFI::addr($pubkey),
            self::EC_COMPRESSED_FLAG,
        )) {
            throw new LogicException('ECDH failed to serialise shared point');
        }

        return substr(FFI::string($output, self::COMPRESSED_PUBKEY_LENGTH), 1, self::XONLY_PUBKEY_LENGTH);
    }

    public function __destruct()
    {
        $this->ffi->secp256k1_context_destroy($this->context);
    }
}
