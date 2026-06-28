<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use FFI;
use FFI\CData;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Throwable;

final class LibSecp256k1Ffi
{
    private const string CDEF = <<<'C'
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

    private const int CONTEXT_SIGN_VERIFY = 769;
    private const int CONTEXT_SEED_LENGTH = 32;
    private const int EC_COMPRESSED_FLAG = 258;
    private const int COMPRESSED_PUBKEY_LENGTH = 33;
    private const int XONLY_PUBKEY_LENGTH = 32;

    private const array LIBRARY_NAMES = [
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

    public static function tryLoad(?RandomBytesGeneratorInterface $randomBytes = null): ?self
    {
        $ffi = FfiLibraryLoader::tryLoad(self::CDEF, self::LIBRARY_NAMES);
        if (null === $ffi) {
            return null;
        }

        $seed32 = ($randomBytes ?? new NativeRandomBytesGenerator())->bytes(self::CONTEXT_SEED_LENGTH);
        $context = null;

        try {
            $context = $ffi->secp256k1_context_create(self::CONTEXT_SIGN_VERIFY);
            if (1 === $ffi->secp256k1_context_randomize($context, FfiLibraryLoader::toBuffer($ffi, $seed32))) {
                return new self($ffi, $context);
            }
        } catch (Throwable) {
            // Deliberate: a loadable-but-incompatible native library is a failed capability probe, not a fault — fall through to the pure-PHP path rather than propagate — see ADR-0025
        }

        if (null !== $context) {
            $ffi->secp256k1_context_destroy($context);
        }

        return null;
    }

    public function sign(string $messageBytes, string $privkeyBytes, string $auxRand32): string
    {
        return $this->withKeypair($privkeyBytes, function (CData $keypair) use ($messageBytes, $auxRand32): string {
            $sig = $this->ffi->new('unsigned char[64]');

            if (1 !== $this->ffi->secp256k1_schnorrsig_sign32($this->context, $sig, FfiLibraryLoader::toBuffer($this->ffi, $messageBytes), FFI::addr($keypair), FfiLibraryLoader::toBuffer($this->ffi, $auxRand32))) {
                throw new CryptoException('Schnorr signing failed');
            }

            return FFI::string($sig, 64);
        });
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
        return $this->withKeypair($privkeyBytes, function (CData $keypair): string {
            $pubkey = $this->ffi->new('secp256k1_xonly_pubkey');
            if (1 !== $this->ffi->secp256k1_keypair_xonly_pub($this->context, FFI::addr($pubkey), null, FFI::addr($keypair))) {
                throw new CryptoException('Failed to derive x-only public key from keypair');
            }

            $output = $this->ffi->new('unsigned char[32]');
            if (1 !== $this->ffi->secp256k1_xonly_pubkey_serialize($this->context, $output, FFI::addr($pubkey))) {
                throw new CryptoException('Failed to serialise x-only public key');
            }

            return FFI::string($output, 32);
        });
    }

    /**
     * @template T
     *
     * @param callable(CData): T $use
     *
     * @return T
     */
    private function withKeypair(string $privkeyBytes, callable $use): mixed
    {
        $keypair = $this->ffi->new('secp256k1_keypair');
        $privkeyBuffer = FfiLibraryLoader::toBuffer($this->ffi, $privkeyBytes);

        try {
            if (1 !== $this->ffi->secp256k1_keypair_create($this->context, FFI::addr($keypair), $privkeyBuffer)) {
                throw new CryptoException('Failed to create keypair from private key');
            }

            return $use($keypair);
        } finally {
            FFI::memset($privkeyBuffer, 0, strlen($privkeyBytes));
            FFI::memset(FFI::addr($keypair), 0, FFI::sizeof($keypair));
        }
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
            throw new EcdhException('ECDH public key is not a valid curve point');
        }

        $privkeyBuffer = FfiLibraryLoader::toBuffer($this->ffi, $privkeyBytes);
        $output = $this->ffi->new('unsigned char['.self::COMPRESSED_PUBKEY_LENGTH.']');

        try {
            if (1 !== $this->ffi->secp256k1_ec_pubkey_tweak_mul($this->context, FFI::addr($pubkey), $privkeyBuffer)) {
                throw new EcdhException('ECDH shared point is the identity');
            }

            $outputLen = $this->ffi->new('size_t');
            $outputLen->cdata = self::COMPRESSED_PUBKEY_LENGTH;

            if (1 !== $this->ffi->secp256k1_ec_pubkey_serialize(
                $this->context,
                $output,
                FFI::addr($outputLen),
                FFI::addr($pubkey),
                self::EC_COMPRESSED_FLAG,
            )) {
                throw new EcdhException('ECDH failed to serialise shared point');
            }

            return substr(FFI::string($output, self::COMPRESSED_PUBKEY_LENGTH), 1, self::XONLY_PUBKEY_LENGTH);
        } finally {
            FFI::memset($privkeyBuffer, 0, strlen($privkeyBytes));
            FFI::memset($output, 0, self::COMPRESSED_PUBKEY_LENGTH);
            FFI::memset(FFI::addr($pubkey), 0, FFI::sizeof($pubkey));
        }
    }

    public function __destruct()
    {
        $this->ffi->secp256k1_context_destroy($this->context);
    }
}
