<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use GMP;
use Innis\Nostr\Core\Domain\Service\CashuDleqVerifierInterface;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Mdanter\Ecc\Primitives\PointInterface;
use Override;

final readonly class CashuDleqVerifier implements CashuDleqVerifierInterface
{
    private const int SCALAR_LENGTH = 32;
    private const int COMPRESSED_POINT_LENGTH = 33;

    #[Override]
    public function isValid(NutzapProof $proof, string $mintKey): bool
    {
        $dleq = $proof->getDleq();
        $secret = $proof->getSecret();
        $e = self::tryReadScalar($dleq?->getE());
        $s = self::tryReadScalar($dleq?->getS());
        $r = self::tryReadScalar($dleq?->getR());
        $mintPublicKey = self::tryReadPoint($mintKey);
        $unblindedSignature = self::tryReadPoint($proof->getUnblindedSignature());
        $hashedSecret = null === $secret ? null : Secp256k1Math::cashuHashToCurve($secret);

        if (null === $e || null === $s || null === $r || null === $mintPublicKey || null === $unblindedSignature || null === $hashedSecret) {
            return false;
        }

        $generator = Secp256k1Math::generator();
        $blindedSignature = $unblindedSignature->add($mintPublicKey->mul($r));
        $blindedMessage = $hashedSecret->add($generator->mul($r));
        $r1 = $generator->mul($s)->add(Secp256k1Math::negate($mintPublicKey->mul($e)));
        $r2 = $blindedMessage->mul($s)->add(Secp256k1Math::negate($blindedSignature->mul($e)));

        return !array_any([$blindedSignature, $blindedMessage, $r1, $r2], static fn (PointInterface $point): bool => $point->isInfinity())
            && hash_equals(Secp256k1Math::gmpToBytes($e, self::SCALAR_LENGTH), self::challenge([$r1, $r2, $mintPublicKey, $blindedSignature]));
    }

    /**
     * @param list<PointInterface> $points
     */
    private static function challenge(array $points): string
    {
        $uncompressedHex = array_map(static fn (PointInterface $point): string => bin2hex(Secp256k1Math::serialiseUncompressed($point)), $points);

        return hash('sha256', implode('', $uncompressedHex), true);
    }

    private static function tryReadScalar(?string $hex): ?GMP
    {
        $bytes = self::tryReadHex($hex, self::SCALAR_LENGTH);
        $scalar = null === $bytes ? null : gmp_import($bytes);

        return null !== $scalar && gmp_cmp($scalar, 0) > 0 && gmp_cmp($scalar, Secp256k1Math::generator()->getOrder()) < 0 ? $scalar : null;
    }

    // Deliberate: a point is read only compressed, as NUT-00 writes it, though cashu-ts also reads an uncompressed C — see nostr-adrs ADR-0121
    private static function tryReadPoint(?string $hex): ?PointInterface
    {
        $bytes = self::tryReadHex($hex, self::COMPRESSED_POINT_LENGTH);

        return null === $bytes ? null : Secp256k1Math::decompressPoint($bytes);
    }

    private static function tryReadHex(?string $hex, int $byteLength): ?string
    {
        return null !== $hex && strlen($hex) === $byteLength * 2 && ctype_xdigit($hex) ? sodium_hex2bin($hex) : null;
    }
}
