<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Backend;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Secp256k1BackendTest extends TestCase
{
    public function testSignatureServiceInterfaceDoesNotExposeTheBackend(): void
    {
        $this->assertFalse(new ReflectionClass(SignatureServiceInterface::class)->hasMethod('backend'));
    }

    public function testEcdhServiceInterfaceDoesNotExposeTheBackend(): void
    {
        $this->assertFalse(new ReflectionClass(EcdhServiceInterface::class)->hasMethod('backend'));
    }

    public function testBackendStaysOutOfTheDomainLayer(): void
    {
        $this->assertSame(
            'Innis\Nostr\Core\Infrastructure\Crypto',
            new ReflectionClass(Secp256k1Backend::class)->getNamespaceName(),
        );
    }
}
