<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use InvalidArgumentException;
use Override;
use Throwable;

final readonly class Secp256k1Signer implements SignatureServiceInterface
{
    private const int SCHNORR_MESSAGE_LENGTH = 32;
    private const int AUX_RAND_LENGTH = 32;

    public function __construct(
        private ?LibSecp256k1Ffi $ffi,
        private RandomBytesGeneratorInterface $randomBytes,
    ) {
    }

    public static function create(RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator()): self
    {
        // Deliberate: probes for native libsecp256k1; absent it, signing uses the pure-PHP path, which is NOT constant-time — prefer the native path for server-side signers, see ADR-0101
        $ffi = LibSecp256k1Ffi::tryLoad($randomBytes);

        return new self($ffi, $randomBytes);
    }

    // Deliberate: deployment introspection only, and deliberately absent from SignatureServiceInterface so no domain or application code can branch on the backend — see ADR-0101
    public function backend(): Secp256k1Backend
    {
        return null !== $this->ffi ? Secp256k1Backend::Native : Secp256k1Backend::PurePhp;
    }

    #[Override]
    public function sign(PrivateKey $privateKey, string $message): Signature
    {
        // Deliberate: rejects non-32-byte messages so a wrong-length argument fails fast rather than diverging code paths — see ADR-0013
        if (self::SCHNORR_MESSAGE_LENGTH !== strlen($message)) {
            throw new InvalidArgumentException(sprintf('Nostr signs a 32-byte event id; got %d bytes', strlen($message)));
        }

        return $privateKey->expose(function (string $privkeyBytes) use ($message): Signature {
            // Deliberate: native libsecp256k1 when present, pure-PHP fallback otherwise; one contract, both pinned by the conformance suites — see ADR-0101
            if (null !== $this->ffi) {
                $auxRand = $this->randomBytes->bytes(self::AUX_RAND_LENGTH);

                return Signature::tryFromBytes($this->ffi->sign($message, $privkeyBytes, $auxRand))
                    ?? throw new CryptoException('FFI signing produced invalid signature');
            }

            return $this->signPurePhp($privkeyBytes, $message);
        });
    }

    #[Override]
    public function verify(PublicKey $publicKey, string $message, Signature $signature): bool
    {
        // Deliberate: verify stays length-agnostic (unlike sign) — see ADR-0013; native/pure-PHP dispatch — see ADR-0101
        try {
            if (null !== $this->ffi) {
                return $this->ffi->verify($signature->toBytes(), $message, $publicKey->toBytes());
            }

            return $this->verifyPurePhp($message, $signature, $publicKey);
        } catch (Throwable) {
            // Deliberate: verify is a total predicate — any internal failure is "not a valid signature", never a propagated fault — see ADR-0027
            return false;
        }
    }

    #[Override]
    public function derivePublicKey(PrivateKey $privateKey): PublicKey
    {
        return $privateKey->expose(function (string $privkeyBytes): PublicKey {
            // Deliberate: native libsecp256k1 when present, pure-PHP fallback otherwise — see ADR-0101
            if (null !== $this->ffi) {
                return PublicKey::tryFromBytes($this->ffi->derivePublicKey($privkeyBytes))
                    ?? throw new CryptoException('Key derivation produced invalid public key');
            }

            return $this->derivePublicKeyPurePhp($privkeyBytes);
        });
    }

    private function derivePublicKeyPurePhp(string $privkeyBytes): PublicKey
    {
        $publicKeyPoint = Secp256k1Math::generator()->mul(gmp_import($privkeyBytes));

        return PublicKey::tryFromBytes(Secp256k1Math::gmpToBytes($publicKeyPoint->getX(), PublicKey::BYTE_LENGTH))
            ?? throw new CryptoException('Key derivation produced invalid public key');
    }

    // Deliberate: BIP-340 is composed here over paragonie/ecc arithmetic rather than through its SchnorrSigner, which takes secrets as unwiped hex and reads its message by content — see ADR-0122
    private function signPurePhp(string $privkeyBytes, string $message): Signature
    {
        $generator = Secp256k1Math::generator();
        $n = $generator->getOrder();

        $privateKeyInt = gmp_import($privkeyBytes);

        $P = $generator->mul($privateKeyInt);
        $d = 0 === gmp_cmp(gmp_mod($P->getY(), 2), 0) ? $privateKeyInt : gmp_sub($n, $privateKeyInt);

        $aux = $this->randomBytes->bytes(self::AUX_RAND_LENGTH);
        $dBytes = Secp256k1Math::gmpToBytes($d, 32);
        $t = $dBytes ^ Secp256k1Math::taggedHash('BIP0340/aux', $aux);
        sodium_memzero($dBytes);

        $randInput = $t.Secp256k1Math::gmpToBytes($P->getX(), 32).$message;
        $rand = Secp256k1Math::taggedHash('BIP0340/nonce', $randInput);
        sodium_memzero($t);
        sodium_memzero($randInput);

        $kPrime = Secp256k1Math::reduceToScalar($rand);
        sodium_memzero($rand);

        if (0 === gmp_cmp($kPrime, 0)) {
            throw new CryptoException('BIP-340 nonce generation produced zero value');
        }

        $R = $generator->mul($kPrime);
        $k = 0 === gmp_cmp(gmp_mod($R->getY(), 2), 0) ? $kPrime : gmp_sub($n, $kPrime);

        $e = Secp256k1Math::challenge($R->getX(), $P->getX(), $message);

        $s = gmp_mod(gmp_add($k, gmp_mul($e, $d)), $n);

        return Signature::tryFromBytes(Secp256k1Math::gmpToBytes($R->getX(), 32).Secp256k1Math::gmpToBytes($s, 32))
            ?? throw new CryptoException('Schnorr signing produced invalid signature');
    }

    private function verifyPurePhp(string $message, Signature $signature, PublicKey $publicKey): bool
    {
        $generator = Secp256k1Math::generator();
        $curve = Secp256k1Math::curve();

        $p = $curve->getPrime();
        $n = $generator->getOrder();

        $P_x = gmp_import($publicKey->toBytes());
        $P = Secp256k1Math::liftX($P_x);

        if (null === $P) {
            return false;
        }

        $signatureBytes = $signature->toBytes();
        $r = gmp_import(substr($signatureBytes, 0, 32));
        $s = gmp_import(substr($signatureBytes, 32));

        if (gmp_cmp($r, $p) >= 0 || gmp_cmp($s, $n) >= 0) {
            return false;
        }

        $e = Secp256k1Math::challenge($r, $P_x, $message);

        $sG = $generator->mul($s);
        $eP = $P->mul($e);

        $negEP_y = gmp_sub($p, $eP->getY());
        $negEP = $curve->getPoint($eP->getX(), $negEP_y);
        $R = $sG->add($negEP);

        if ($R->isInfinity()) {
            return false;
        }

        if (0 !== gmp_cmp(gmp_mod($R->getY(), 2), 0)) {
            return false;
        }

        return 0 === gmp_cmp($R->getX(), $r);
    }
}
