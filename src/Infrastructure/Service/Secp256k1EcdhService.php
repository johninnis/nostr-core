<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use LogicException;
use Mdanter\Ecc\EccFactory;

final class Secp256k1EcdhService implements EcdhServiceInterface
{
    private const SHARED_X_HEX_LENGTH = 64;

    public function computeSharedX(PrivateKey $privateKey, PublicKey $publicKey): string
    {
        $adapter = EccFactory::getAdapter();
        $curve = EccFactory::getSecgCurves($adapter)->curve256k1();

        $publicKeyX = gmp_init($publicKey->toHex(), 16);
        if (gmp_cmp($publicKeyX, 0) <= 0 || gmp_cmp($publicKeyX, $curve->getPrime()) >= 0) {
            throw new LogicException('ECDH public key x-coordinate out of field range');
        }

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

            $sharedXHex = str_pad(gmp_strval($sharedPoint->getX(), 16), self::SHARED_X_HEX_LENGTH, '0', STR_PAD_LEFT);
            $sharedXBytes = hex2bin($sharedXHex);
            sodium_memzero($sharedXHex);

            if (false === $sharedXBytes) {
                throw new LogicException('ECDH produced invalid shared secret');
            }

            return $sharedXBytes;
        });
        assert(is_string($sharedX));

        return $sharedX;
    }
}
