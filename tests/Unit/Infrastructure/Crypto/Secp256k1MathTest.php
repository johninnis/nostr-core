<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Crypto;

use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Math;
use Mdanter\Ecc\EccFactory;
use PHPUnit\Framework\TestCase;

final class Secp256k1MathTest extends TestCase
{
    public function testLiftXReturnsNullForOffCurveXCoordinate(): void
    {
        $curve = EccFactory::getSecgCurves(EccFactory::getAdapter())->curve256k1();

        // BIP-340 test vector 7: x explicitly marked "public key not on the curve".
        $offCurveX = gmp_init('EEFDEA4CDB677750A420FEE807EACF21EB9898AE79B9768766E4FAA04A2D4A34', 16);

        $this->assertNull(Secp256k1Math::liftX($offCurveX, $curve, $curve->getPrime()));
    }

    public function testLiftXReturnsEvenYPointForOnCurveXCoordinate(): void
    {
        $curve = EccFactory::getSecgCurves(EccFactory::getAdapter())->curve256k1();

        // BIP-340 test vector 1: a valid on-curve pubkey x-coordinate.
        $onCurveX = gmp_init('DFF1D77F2A671C5F36183726DB2341BE58FEAE1DA2DECED843240F7B502BA659', 16);

        $point = Secp256k1Math::liftX($onCurveX, $curve, $curve->getPrime());

        $this->assertNotNull($point);
        $this->assertSame(0, gmp_cmp($point->getX(), $onCurveX));
        $this->assertSame(0, gmp_cmp(gmp_mod($point->getY(), 2), 0));
    }
}
