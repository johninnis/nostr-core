<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

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
        return self::$signer ??= Secp256k1Signer::create();
    }

    public static function ecdh(): EcdhServiceInterface
    {
        return self::$ecdh ??= Secp256k1Ecdh::create();
    }
}
