<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Exception;
use GMP;
use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Primitives\CurveFpInterface;
use Mdanter\Ecc\Primitives\GeneratorPoint;
use Mdanter\Ecc\Primitives\PointInterface;

final class Secp256k1Math
{
    public static function generator(): GeneratorPoint
    {
        return EccFactory::getSecgCurves()->generator256k1();
    }

    public static function curve(): CurveFpInterface
    {
        return EccFactory::getSecgCurves()->curve256k1();
    }

    public static function scalarFromBytes(string $bytes): GMP
    {
        $hex = bin2hex($bytes);

        try {
            return gmp_init($hex, 16);
        } finally {
            sodium_memzero($hex);
        }
    }

    public static function taggedHash(string $tag, string $msg): string
    {
        $tagHash = hash('sha256', $tag, true);

        return hash('sha256', $tagHash.$tagHash.$msg, true);
    }

    public static function gmpToHex(GMP $value, int $byteLength): string
    {
        return str_pad(gmp_strval($value, 16), $byteLength * 2, '0', STR_PAD_LEFT);
    }

    public static function gmpToBytes(GMP $value, int $length): string
    {
        $hex = self::gmpToHex($value, $length);

        try {
            $bytes = hex2bin($hex);
            if (false === $bytes) {
                throw new CryptoException('GMP value produced invalid hex');
            }

            return $bytes;
        } finally {
            sodium_memzero($hex);
        }
    }

    public static function liftX(GMP $x, CurveFpInterface $curve, GMP $p): ?PointInterface
    {
        $x_cubed = gmp_powm($x, 3, $p);
        $y_squared = gmp_mod(gmp_add($x_cubed, 7), $p);

        $exp = gmp_div_q(gmp_add($p, 1), 4);
        $y = gmp_powm($y_squared, $exp, $p);

        if (0 !== gmp_cmp(gmp_mod($y, 2), 0)) {
            $y = gmp_sub($p, $y);
        }

        try {
            return $curve->getPoint($x, $y);
        } catch (Exception) {
            return null;
        }
    }
}
