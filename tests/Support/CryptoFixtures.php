<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

final class CryptoFixtures
{
    private static ?SignatureServiceInterface $signer = null;
    private static ?EcdhServiceInterface $ecdh = null;

    public static function signer(): SignatureServiceInterface
    {
        if (null === self::$signer) {
            try {
                self::$signer = Secp256k1Signer::create();
            } catch (CryptoException) {
                self::$signer = Secp256k1Signer::createPurePhp();
            }
        }

        return self::$signer;
    }

    public static function ecdh(): EcdhServiceInterface
    {
        if (null === self::$ecdh) {
            try {
                self::$ecdh = Secp256k1Ecdh::create();
            } catch (CryptoException) {
                self::$ecdh = Secp256k1Ecdh::createPurePhp();
            }
        }

        return self::$ecdh;
    }
}
