<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Infrastructure\Service\LibSecp256k1Ffi;
use Innis\Nostr\Core\Infrastructure\Service\NativeRandomBytesGeneratorAdapter;
use Innis\Nostr\Core\Infrastructure\Service\Secp256k1EcdhService;
use Innis\Nostr\Core\Infrastructure\Service\Secp256k1SignatureService;

trait WithCryptoServices
{
    private ?SignatureServiceInterface $signatureService = null;
    private ?EcdhServiceInterface $ecdhService = null;

    protected function signatureService(): SignatureServiceInterface
    {
        if (null === $this->signatureService) {
            $randomBytes = new NativeRandomBytesGeneratorAdapter();
            $this->signatureService = new Secp256k1SignatureService(
                LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32)),
                $randomBytes,
            );
        }

        return $this->signatureService;
    }

    protected function ecdhService(): EcdhServiceInterface
    {
        if (null === $this->ecdhService) {
            $this->ecdhService = Secp256k1EcdhService::create();
        }

        return $this->ecdhService;
    }
}
