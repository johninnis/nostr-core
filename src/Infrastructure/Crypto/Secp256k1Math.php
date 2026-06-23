<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use GMP;
use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Primitives\CurveFpInterface;
use Mdanter\Ecc\Primitives\GeneratorPoint;

final class Secp256k1Math
{
    private function __construct()
    {
    }

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
}
