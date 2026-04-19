<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Exception;
use GMP;
use LogicException;
use Mdanter\Ecc\Primitives\CurveFpInterface;
use Mdanter\Ecc\Primitives\PointInterface;

final class Secp256k1Math
{
    public static function taggedHash(string $tag, string $msg): string
    {
        $tagHash = hash('sha256', $tag, true);

        return hash('sha256', $tagHash.$tagHash.$msg, true);
    }

    public static function gmpToBytes(GMP $value, int $length): string
    {
        $hex = str_pad(gmp_strval($value, 16), $length * 2, '0', STR_PAD_LEFT);

        try {
            $bytes = hex2bin($hex);
            if (false === $bytes) {
                throw new LogicException('GMP value produced invalid hex');
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
