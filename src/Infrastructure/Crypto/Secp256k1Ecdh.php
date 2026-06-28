<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;
use Throwable;

final class Secp256k1Ecdh implements EcdhServiceInterface
{
    private const int SHARED_X_BYTE_LENGTH = 32;

    public function __construct(private readonly ?LibSecp256k1Ffi $ffi)
    {
    }

    public static function create(?RandomBytesGeneratorInterface $randomBytes = null): self
    {
        return new self(LibSecp256k1Ffi::tryLoad($randomBytes));
    }

    #[Override]
    public function computeSharedX(PrivateKey $privateKey, PublicKey $publicKey): string
    {
        $pubkeyHex = $publicKey->toHex();

        $this->assertXCoordinateInFieldRange($pubkeyHex);

        // Deliberate: native libsecp256k1 when present, pure-PHP fallback otherwise; both pinned by the ECDH parity suite — see ADR-0025
        if (null !== $this->ffi) {
            return $this->computeSharedXFfi($privateKey, $publicKey);
        }

        return $this->computeSharedXPurePhp($privateKey, $pubkeyHex);
    }

    // Deliberate: this gmp check runs ahead of the native/FFI dispatch above; gmp is a hard dependency (paragonie/ecc requires it), so there is no FFI-without-gmp host to keep it free of — do not "tidy" it back to a gmp-free string comparison — see ADR-0025
    private function assertXCoordinateInFieldRange(string $pubkeyHex): void
    {
        if (!Secp256k1Math::isXCoordinateInField(gmp_init($pubkeyHex, 16))) {
            throw new EcdhException('ECDH public key x-coordinate out of field range');
        }
    }

    private function computeSharedXFfi(PrivateKey $privateKey, PublicKey $publicKey): string
    {
        $ffi = $this->ffi;
        assert(null !== $ffi);

        $pubkeyBytes = $publicKey->toBytes();

        return $privateKey->expose(static fn (string $privkeyBytes): string => $ffi->computeSharedX($privkeyBytes, $pubkeyBytes));
    }

    private function computeSharedXPurePhp(PrivateKey $privateKey, string $pubkeyHex): string
    {
        $curve = Secp256k1Math::curve();

        $publicKeyX = gmp_init($pubkeyHex, 16);

        try {
            $publicKeyY = $curve->recoverYfromX(false, $publicKeyX);
            $publicKeyPoint = $curve->getPoint($publicKeyX, $publicKeyY);
        } catch (Throwable $exception) {
            throw new EcdhException('ECDH public key is not a valid curve point', 0, $exception);
        }

        return $privateKey->expose(static function (string $privkeyBytes) use ($publicKeyPoint): string {
            $privateKeyInt = Secp256k1Math::scalarFromBytes($privkeyBytes);

            $sharedPoint = $publicKeyPoint->mul($privateKeyInt);
            if ($sharedPoint->isInfinity()) {
                throw new EcdhException('ECDH shared point is the identity');
            }

            return Secp256k1Math::gmpToBytes($sharedPoint->getX(), self::SHARED_X_BYTE_LENGTH);
        });
    }
}
