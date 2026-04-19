<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\InvalidSignatureException;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use LogicException;
use Mdanter\Ecc\EccFactory;
use Throwable;

final class Secp256k1SignatureService implements SignatureServiceInterface
{
    private const SIGNATURE_HEX_LENGTH = 128;
    private const SCHNORR_MESSAGE_LENGTH = 32;
    private const AUX_RAND_LENGTH = 32;

    public function __construct(
        private readonly ?LibSecp256k1Ffi $ffi,
        private readonly RandomBytesGeneratorInterface $randomBytes,
    ) {
    }

    public static function create(?RandomBytesGeneratorInterface $randomBytes = null): self
    {
        $randomBytes ??= new NativeRandomBytesGeneratorAdapter();
        $seed = $randomBytes->bytes(32);
        $ffi = LibSecp256k1Ffi::tryLoad($seed);

        return new self($ffi, $randomBytes);
    }

    public function sign(PrivateKey $privateKey, string $message): Signature
    {
        $signature = $privateKey->expose(function (string $privkeyBytes) use ($message): Signature {
            if (null !== $this->ffi && self::SCHNORR_MESSAGE_LENGTH === strlen($message)) {
                $auxRand = $this->randomBytes->bytes(self::AUX_RAND_LENGTH);

                return Signature::fromHex(bin2hex($this->ffi->sign($message, $privkeyBytes, $auxRand)))
                    ?? throw new LogicException('FFI signing produced invalid signature');
            }

            return $this->signPurePhp($privkeyBytes, $message);
        });
        assert($signature instanceof Signature);

        return $signature;
    }

    public function verify(PublicKey $publicKey, string $message, Signature $signature): bool
    {
        try {
            $signatureHex = $signature->toHex();
            if (self::SIGNATURE_HEX_LENGTH !== strlen($signatureHex)) {
                return false;
            }

            if (null !== $this->ffi) {
                $sigBytes = hex2bin($signatureHex);
                $pubkeyBytes = hex2bin($publicKey->toHex());
                if (false === $sigBytes || false === $pubkeyBytes) {
                    return false;
                }

                return $this->ffi->verify($sigBytes, $message, $pubkeyBytes);
            }

            return $this->verifyPurePhp($message, $signatureHex, $publicKey->toHex());
        } catch (Throwable) {
            return false;
        }
    }

    public function derivePublicKey(PrivateKey $privateKey): PublicKey
    {
        $publicKey = $privateKey->expose(function (string $privkeyBytes): PublicKey {
            if (null !== $this->ffi) {
                return PublicKey::fromHex(bin2hex($this->ffi->derivePublicKey($privkeyBytes)))
                    ?? throw new LogicException('Key derivation produced invalid public key');
            }

            return $this->derivePublicKeyPurePhp($privkeyBytes);
        });
        assert($publicKey instanceof PublicKey);

        return $publicKey;
    }

    private function derivePublicKeyPurePhp(string $privkeyBytes): PublicKey
    {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();

        $privateKeyHex = bin2hex($privkeyBytes);
        try {
            $privateKeyInt = gmp_init($privateKeyHex, 16);
        } finally {
            sodium_memzero($privateKeyHex);
        }

        $publicKeyPoint = $generator->mul($privateKeyInt);

        if (0 !== gmp_cmp(gmp_mod($publicKeyPoint->getY(), 2), 0)) {
            $privateKeyInt = gmp_sub($generator->getOrder(), $privateKeyInt);
            $publicKeyPoint = $generator->mul($privateKeyInt);
        }

        $publicKeyHex = str_pad(gmp_strval($publicKeyPoint->getX(), 16), 64, '0', STR_PAD_LEFT);

        return PublicKey::fromHex($publicKeyHex)
            ?? throw new LogicException('Key derivation produced invalid public key');
    }

    private function signPurePhp(string $privkeyBytes, string $message): Signature
    {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $n = $generator->getOrder();

        $privateKeyHex = bin2hex($privkeyBytes);
        try {
            $privateKeyInt = gmp_init($privateKeyHex, 16);
        } finally {
            sodium_memzero($privateKeyHex);
        }

        $P = $generator->mul($privateKeyInt);
        $d = 0 === gmp_cmp(gmp_mod($P->getY(), 2), 0) ? $privateKeyInt : gmp_sub($n, $privateKeyInt);

        $aux = $this->randomBytes->bytes(self::AUX_RAND_LENGTH);
        $dBytes = SchnorrMathHelper::gmpToBytes($d, 32);
        $t = $this->xorBytes($dBytes, SchnorrMathHelper::taggedHash('BIP0340/aux', $aux));
        sodium_memzero($dBytes);

        $randInput = $t.SchnorrMathHelper::gmpToBytes($P->getX(), 32).$message;
        $rand = SchnorrMathHelper::taggedHash('BIP0340/nonce', $randInput);
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
            throw new InvalidSignatureException('BIP-340 nonce generation produced zero value');
        }

        $R = $generator->mul($kPrime);
        $k = 0 === gmp_cmp(gmp_mod($R->getY(), 2), 0) ? $kPrime : gmp_sub($n, $kPrime);

        $eInput = SchnorrMathHelper::gmpToBytes($R->getX(), 32).SchnorrMathHelper::gmpToBytes($P->getX(), 32).$message;
        $eHash = SchnorrMathHelper::taggedHash('BIP0340/challenge', $eInput);
        $e = gmp_mod(gmp_init(bin2hex($eHash), 16), $n);

        $s = gmp_mod(gmp_add($k, gmp_mul($e, $d)), $n);

        $rHex = str_pad(gmp_strval($R->getX(), 16), 64, '0', STR_PAD_LEFT);
        $sHex = str_pad(gmp_strval($s, 16), 64, '0', STR_PAD_LEFT);

        return Signature::fromHex($rHex.$sHex)
            ?? throw new LogicException('Schnorr signing produced invalid signature');
    }

    private function verifyPurePhp(string $message, string $signatureHex, string $publicKeyHex): bool
    {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $curve = EccFactory::getSecgCurves($adapter)->curve256k1();

        $p = $curve->getPrime();
        $n = $generator->getOrder();

        $P_x = gmp_init($publicKeyHex, 16);
        $P = SchnorrMathHelper::liftX($P_x, $curve, $p);
        if (null === $P) {
            return false;
        }

        $r = gmp_init(substr($signatureHex, 0, 64), 16);
        $s = gmp_init(substr($signatureHex, 64, 64), 16);

        if (gmp_cmp($r, $p) >= 0 || gmp_cmp($s, $n) >= 0) {
            return false;
        }

        $eInput = SchnorrMathHelper::gmpToBytes($r, 32).SchnorrMathHelper::gmpToBytes($P_x, 32).$message;
        $eHash = SchnorrMathHelper::taggedHash('BIP0340/challenge', $eInput);
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

    private function xorBytes(string $a, string $b): string
    {
        $result = '';
        $length = min(strlen($a), strlen($b));
        for ($i = 0; $i < $length; ++$i) {
            $result .= chr((ord($a[$i]) ^ ord($b[$i])) & 0xFF);
        }

        return $result;
    }
}
