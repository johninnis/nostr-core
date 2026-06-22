<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Exception;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use InvalidArgumentException;
use Override;

final class Secp256k1Signer implements SignatureServiceInterface
{
    private const int SCHNORR_MESSAGE_LENGTH = 32;
    private const int AUX_RAND_LENGTH = 32;

    public function __construct(
        private readonly ?LibSecp256k1Ffi $ffi,
        private readonly RandomBytesGeneratorInterface $randomBytes,
    ) {
    }

    public static function create(?RandomBytesGeneratorInterface $randomBytes = null): self
    {
        $randomBytes ??= new NativeRandomBytesGenerator();
        $seed = $randomBytes->bytes(32);
        $ffi = LibSecp256k1Ffi::tryLoad($seed);

        return new self($ffi, $randomBytes);
    }

    #[Override]
    public function sign(PrivateKey $privateKey, string $message): Signature
    {
        // Deliberate: rejects non-32-byte messages so a wrong-length argument fails fast rather than diverging code paths — see ADR-0013
        if (self::SCHNORR_MESSAGE_LENGTH !== strlen($message)) {
            throw new InvalidArgumentException(sprintf('Nostr signs a 32-byte event id; got %d bytes', strlen($message)));
        }

        return $privateKey->expose(function (string $privkeyBytes) use ($message): Signature {
            if (null !== $this->ffi) {
                $auxRand = $this->randomBytes->bytes(self::AUX_RAND_LENGTH);

                return Signature::fromBytes($this->ffi->sign($message, $privkeyBytes, $auxRand))
                    ?? throw new CryptoException('FFI signing produced invalid signature');
            }

            return $this->signPurePhp($privkeyBytes, $message);
        });
    }

    #[Override]
    public function verify(PublicKey $publicKey, string $message, Signature $signature): bool
    {
        if (null !== $this->ffi) {
            return $this->ffi->verify($signature->toBytes(), $message, $publicKey->toBytes());
        }

        return $this->verifyPurePhp($message, $signature->toHex(), $publicKey->toHex());
    }

    #[Override]
    public function derivePublicKey(PrivateKey $privateKey): PublicKey
    {
        $publicKey = $privateKey->expose(function (string $privkeyBytes): PublicKey {
            if (null !== $this->ffi) {
                return PublicKey::fromBytes($this->ffi->derivePublicKey($privkeyBytes))
                    ?? throw new CryptoException('Key derivation produced invalid public key');
            }

            return $this->derivePublicKeyPurePhp($privkeyBytes);
        });

        return $publicKey;
    }

    private function derivePublicKeyPurePhp(string $privkeyBytes): PublicKey
    {
        $generator = Secp256k1Math::generator();

        $privateKeyInt = Secp256k1Math::scalarFromBytes($privkeyBytes);
        $publicKeyPoint = $generator->mul($privateKeyInt);

        if (0 !== gmp_cmp(gmp_mod($publicKeyPoint->getY(), 2), 0)) {
            $privateKeyInt = gmp_sub($generator->getOrder(), $privateKeyInt);
            $publicKeyPoint = $generator->mul($privateKeyInt);
        }

        return PublicKey::fromHex(Secp256k1Math::gmpToHex($publicKeyPoint->getX(), 32))
            ?? throw new CryptoException('Key derivation produced invalid public key');
    }

    private function signPurePhp(string $privkeyBytes, string $message): Signature
    {
        $generator = Secp256k1Math::generator();
        $n = $generator->getOrder();

        $privateKeyInt = Secp256k1Math::scalarFromBytes($privkeyBytes);

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

        $randHex = bin2hex($rand);
        sodium_memzero($rand);
        try {
            $kPrime = gmp_mod(gmp_init($randHex, 16), $n);
        } finally {
            sodium_memzero($randHex);
        }

        if (0 === gmp_cmp($kPrime, 0)) {
            throw new CryptoException('BIP-340 nonce generation produced zero value');
        }

        $R = $generator->mul($kPrime);
        $k = 0 === gmp_cmp(gmp_mod($R->getY(), 2), 0) ? $kPrime : gmp_sub($n, $kPrime);

        $eInput = Secp256k1Math::gmpToBytes($R->getX(), 32).Secp256k1Math::gmpToBytes($P->getX(), 32).$message;
        $eHash = Secp256k1Math::taggedHash('BIP0340/challenge', $eInput);
        $e = gmp_mod(gmp_init(bin2hex($eHash), 16), $n);

        $s = gmp_mod(gmp_add($k, gmp_mul($e, $d)), $n);

        $rHex = Secp256k1Math::gmpToHex($R->getX(), 32);
        $sHex = Secp256k1Math::gmpToHex($s, 32);

        return Signature::fromHex($rHex.$sHex)
            ?? throw new CryptoException('Schnorr signing produced invalid signature');
    }

    private function verifyPurePhp(string $message, string $signatureHex, string $publicKeyHex): bool
    {
        $generator = Secp256k1Math::generator();
        $curve = Secp256k1Math::curve();

        $p = $curve->getPrime();
        $n = $generator->getOrder();

        $P_x = gmp_init($publicKeyHex, 16);

        try {
            $P = $curve->getPoint($P_x, $curve->recoverYfromX(false, $P_x));
        } catch (Exception) {
            return false;
        }

        $r = gmp_init(substr($signatureHex, 0, 64), 16);
        $s = gmp_init(substr($signatureHex, 64, 64), 16);

        if (gmp_cmp($r, $p) >= 0 || gmp_cmp($s, $n) >= 0) {
            return false;
        }

        $eInput = Secp256k1Math::gmpToBytes($r, 32).Secp256k1Math::gmpToBytes($P_x, 32).$message;
        $eHash = Secp256k1Math::taggedHash('BIP0340/challenge', $eInput);
        $e = gmp_mod(gmp_init(bin2hex($eHash), 16), $n);

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
