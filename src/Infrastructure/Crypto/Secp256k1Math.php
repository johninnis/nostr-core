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
    private const int COORDINATE_LENGTH = 32;
    private const string EVEN_Y_PREFIX = "\x02";
    private const string ODD_Y_PREFIX = "\x03";
    private const string UNCOMPRESSED_PREFIX = "\x04";
    private const string CASHU_HASH_TO_CURVE_DOMAIN = 'Secp256k1_HashToCurve_Cashu_';
    private const int CASHU_HASH_TO_CURVE_COUNTERS = 65536;

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
        return self::pointWithParity($x, false);
    }

    public static function decompressPoint(string $compressed): ?PointInterface
    {
        $prefix = substr($compressed, 0, 1);

        if (1 + self::COORDINATE_LENGTH !== strlen($compressed) || !in_array($prefix, [self::EVEN_Y_PREFIX, self::ODD_Y_PREFIX], true)) {
            return null;
        }

        return self::pointWithParity(gmp_import(substr($compressed, 1)), self::ODD_Y_PREFIX === $prefix);
    }

    public static function serialiseUncompressed(PointInterface $point): string
    {
        return self::UNCOMPRESSED_PREFIX.self::gmpToBytes($point->getX(), self::COORDINATE_LENGTH).self::gmpToBytes($point->getY(), self::COORDINATE_LENGTH);
    }

    public static function negate(PointInterface $point): PointInterface
    {
        return $point->isInfinity() ? $point : self::curve()->getPoint($point->getX(), gmp_sub(self::curve()->getPrime(), $point->getY()));
    }

    public static function cashuHashToCurve(string $message): ?PointInterface
    {
        $messageHash = hash('sha256', self::CASHU_HASH_TO_CURVE_DOMAIN.$message, true);

        for ($counter = 0; $counter < self::CASHU_HASH_TO_CURVE_COUNTERS; ++$counter) {
            $point = self::decompressPoint(self::EVEN_Y_PREFIX.hash('sha256', $messageHash.pack('V', $counter), true));

            if (null !== $point) {
                return $point;
            }
        }

        return null;
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

    private static function pointWithParity(GMP $x, bool $hasOddY): ?PointInterface
    {
        if (!self::isXCoordinateInField($x)) {
            return null;
        }

        try {
            return self::curve()->getPoint($x, self::curve()->recoverYfromX($hasOddY, $x));
        } catch (PointRecoveryException|PointNotOnCurveException) {
            return null;
        }
    }
}
