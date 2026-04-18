<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Exception;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Mdanter\Ecc\EccFactory;
use Throwable;

final readonly class PublicKey
{
    public const HEX_LENGTH = 64;

    private function __construct(private string $key)
    {
    }

    public function toHex(): string
    {
        return $this->key;
    }

    public function toBech32(): string
    {
        return Bech32Codec::encode('npub', Bech32Codec::hexToBytes($this->key));
    }

    public function verify(string $messageBytes, Signature $signature): bool
    {
        try {
            if (128 !== strlen($signature->toHex())) {
                return false;
            }

            if (Secp256k1::isAvailable()) {
                $sigBytes = hex2bin($signature->toHex());
                $pubkeyBytes = hex2bin($this->key);
                if (false === $sigBytes || false === $pubkeyBytes) {
                    return false;
                }

                return Secp256k1::verify($sigBytes, $messageBytes, $pubkeyBytes);
            }

            return $this->verifySchnorr($messageBytes, $signature->toHex(), $this->key);
        } catch (Throwable) {
            return false;
        }
    }

    private function verifySchnorr(string $message, string $signature, string $publicKey): bool
    {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $curve = EccFactory::getSecgCurves($adapter)->curve256k1();

        $p = $curve->getPrime();
        $n = $generator->getOrder();

        // P = lift_x(int(pk))
        $P_x = gmp_init($publicKey, 16);
        $P = SchnorrMathHelper::liftX($P_x, $curve, $p);
        if (null === $P) {
            return false;
        }

        // r = int(sig[0:32]); s = int(sig[32:64])
        $r = gmp_init(substr($signature, 0, 64), 16);
        $s = gmp_init(substr($signature, 64, 64), 16);

        if (gmp_cmp($r, $p) >= 0 || gmp_cmp($s, $n) >= 0) {
            return false;
        }

        // e = int(hash_BIP0340/challenge(bytes(r) || bytes(P) || m)) mod n
        $eInput = SchnorrMathHelper::gmpToBytes($r, 32).SchnorrMathHelper::gmpToBytes($P_x, 32).$message;
        $eHash = SchnorrMathHelper::taggedHash('BIP0340/challenge', $eInput);
        $e = gmp_mod(gmp_init(bin2hex($eHash), 16), $n);

        // R = s⋅G - e⋅P
        $sG = $generator->mul($s);
        $eP = $P->mul($e);

        // Compute R = sG - eP by negating eP's y-coordinate
        $negEP_y = gmp_sub($p, $eP->getY());
        $negEP = $curve->getPoint($eP->getX(), $negEP_y);
        $R = $sG->add($negEP);

        // Verify conditions
        if ($R->isInfinity()) {
            return false;
        }

        if (0 !== gmp_cmp(gmp_mod($R->getY(), 2), 0)) {
            return false;
        }

        return 0 === gmp_cmp($R->getX(), $r);
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }

    public static function fromBech32(string $bech32): ?self
    {
        if (!str_starts_with($bech32, 'npub1')) {
            return null;
        }

        try {
            $decoded = Bech32Codec::decode($bech32);

            return new self(Bech32Codec::bytesToHex($decoded['data']));
        } catch (Exception) {
            return null;
        }
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
