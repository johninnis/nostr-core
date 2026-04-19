<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Adapter;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Math;
use LogicException;
use Mdanter\Ecc\EccFactory;

final class Secp256k1EcdhAdapter implements EcdhServiceInterface
{
    private const SHARED_X_BYTE_LENGTH = 32;
    private const SECP256K1_PRIME_HEX = 'fffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f';
    private const ZERO_X_HEX = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private readonly ?LibSecp256k1Ffi $ffi = null)
    {
    }

    public static function create(?RandomBytesGeneratorInterface $randomBytes = null): self
    {
        $randomBytes ??= new NativeRandomBytesGeneratorAdapter();
        $seed = $randomBytes->bytes(32);
        $ffi = LibSecp256k1Ffi::tryLoad($seed);

        return new self($ffi);
    }

    public function computeSharedX(PrivateKey $privateKey, PublicKey $publicKey): string
    {
        $pubkeyHex = $publicKey->toHex();

        if (self::ZERO_X_HEX === $pubkeyHex || strcmp($pubkeyHex, self::SECP256K1_PRIME_HEX) >= 0) {
            throw new LogicException('ECDH public key x-coordinate out of field range');
        }

        if (null !== $this->ffi) {
            return $this->computeSharedXFfi($privateKey, $pubkeyHex);
        }

        return $this->computeSharedXPurePhp($privateKey, $pubkeyHex);
    }

    private function computeSharedXFfi(PrivateKey $privateKey, string $pubkeyHex): string
    {
        $ffi = $this->ffi;
        assert(null !== $ffi);

        $pubkeyBytes = hex2bin($pubkeyHex);
        assert(false !== $pubkeyBytes);

        $sharedX = $privateKey->expose(static fn (string $privkeyBytes): string => $ffi->computeSharedX($privkeyBytes, $pubkeyBytes));
        assert(is_string($sharedX));

        return $sharedX;
    }

    private function computeSharedXPurePhp(PrivateKey $privateKey, string $pubkeyHex): string
    {
        $adapter = EccFactory::getAdapter();
        $curve = EccFactory::getSecgCurves($adapter)->curve256k1();

        $publicKeyX = gmp_init($pubkeyHex, 16);
        $publicKeyY = $curve->recoverYfromX(false, $publicKeyX);
        $publicKeyPoint = $curve->getPoint($publicKeyX, $publicKeyY);

        $sharedX = $privateKey->expose(static function (string $privkeyBytes) use ($publicKeyPoint): string {
            $privateKeyHex = bin2hex($privkeyBytes);
            try {
                $privateKeyInt = gmp_init($privateKeyHex, 16);
            } finally {
                sodium_memzero($privateKeyHex);
            }

            $sharedPoint = $publicKeyPoint->mul($privateKeyInt);
            if ($sharedPoint->isInfinity()) {
                throw new LogicException('ECDH shared point is the identity');
            }

            return Secp256k1Math::gmpToBytes($sharedPoint->getX(), self::SHARED_X_BYTE_LENGTH);
        });
        assert(is_string($sharedX));

        return $sharedX;
    }
}
