<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use GMP;
use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Exception\PointNotOnCurveException;
use Mdanter\Ecc\Exception\PointRecoveryException;
use Mdanter\Ecc\Primitives\CurveFpInterface;
use Mdanter\Ecc\Primitives\GeneratorPoint;
use Mdanter\Ecc\Primitives\PointInterface;

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

    public static function isXCoordinateInField(GMP $x): bool
    {
        $prime = self::curve()->getPrime();

        return gmp_cmp($x, 0) > 0 && gmp_cmp($x, $prime) < 0;
    }

    public static function liftX(GMP $x): ?PointInterface
    {
        if (!self::isXCoordinateInField($x)) {
            return null;
        }

        try {
            return self::curve()->getPoint($x, self::curve()->recoverYfromX(false, $x));
        } catch (PointRecoveryException|PointNotOnCurveException) {
            return null;
        }
    }

    public static function taggedHash(string $tag, string $msg): string
    {
        $tagHash = hash('sha256', $tag, true);

        return hash('sha256', $tagHash.$tagHash.$msg, true);
    }

    public static function reduceToScalar(string $bytes): GMP
    {
        return gmp_mod(gmp_import($bytes), self::generator()->getOrder());
    }

    public static function challenge(GMP $signatureX, GMP $publicKeyX, string $message): GMP
    {
        $input = self::gmpToBytes($signatureX, 32).self::gmpToBytes($publicKeyX, 32).$message;

        return self::reduceToScalar(self::taggedHash('BIP0340/challenge', $input));
    }

    public static function gmpToBytes(GMP $value, int $length): string
    {
        $bytes = gmp_export($value);

        try {
            if (strlen($bytes) > $length) {
                throw new CryptoException(sprintf('Value does not fit in %d bytes', $length));
            }

            return str_pad($bytes, $length, "\0", STR_PAD_LEFT);
        } finally {
            sodium_memzero($bytes);
        }
    }
}
