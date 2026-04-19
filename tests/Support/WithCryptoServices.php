<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Infrastructure\Adapter\NativeRandomBytesGeneratorAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1EcdhAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1SignatureAdapter;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;

trait WithCryptoServices
{
    private ?SignatureServiceInterface $signatureService = null;
    private ?EcdhServiceInterface $ecdhService = null;

    protected function signatureService(): SignatureServiceInterface
    {
        if (null === $this->signatureService) {
            $randomBytes = new NativeRandomBytesGeneratorAdapter();
            $this->signatureService = new Secp256k1SignatureAdapter(
                LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32)),
                $randomBytes,
            );
        }

        return $this->signatureService;
    }

    protected function ecdhService(): EcdhServiceInterface
    {
        if (null === $this->ecdhService) {
            $this->ecdhService = Secp256k1EcdhAdapter::create();
        }

        return $this->ecdhService;
    }
}
