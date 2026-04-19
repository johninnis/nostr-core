<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Service\NativeRandomBytesGeneratorAdapter;
use Innis\Nostr\Core\Infrastructure\Service\Secp256k1EcdhService;
use Innis\Nostr\Core\Infrastructure\Service\Secp256k1SignatureService;
use PHPUnit\Framework\TestCase;

final class Secp256k1EcdhServiceTest extends TestCase
{
    public function testComputeSharedXIsSymmetric(): void
    {
        $ecdh = new Secp256k1EcdhService();
        $signer = new Secp256k1SignatureService(null, new NativeRandomBytesGeneratorAdapter());

        $privateKeyA = PrivateKey::generate();
        $privateKeyB = PrivateKey::generate();
        $publicKeyA = $signer->derivePublicKey($privateKeyA);
        $publicKeyB = $signer->derivePublicKey($privateKeyB);

        $aToB = $ecdh->computeSharedX($privateKeyA, $publicKeyB);
        $bToA = $ecdh->computeSharedX($privateKeyB, $publicKeyA);

        $this->assertSame($aToB, $bToA);
    }

    public function testComputeSharedXIsDeterministic(): void
    {
        $ecdh = new Secp256k1EcdhService();
        $signer = new Secp256k1SignatureService(null, new NativeRandomBytesGeneratorAdapter());

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $first = $ecdh->computeSharedX($privateKey, $otherPublicKey);
        $second = $ecdh->computeSharedX($privateKey, $otherPublicKey);

        $this->assertSame($first, $second);
    }

    public function testComputeSharedXMatchesLegacyConversationKeyDerivation(): void
    {
        $ecdh = new Secp256k1EcdhService();
        $signer = new Secp256k1SignatureService(null, new NativeRandomBytesGeneratorAdapter());

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $sharedX = $ecdh->computeSharedX($privateKey, $otherPublicKey);
        $expectedConversationKey = hash_hmac('sha256', $sharedX, 'nip44-v2', true);

        $legacyKey = ConversationKey::derive($privateKey, $otherPublicKey, $ecdh);
        $legacyBytes = $legacyKey->expose(static fn (string $bytes): string => $bytes);

        $this->assertSame($expectedConversationKey, $legacyBytes);
    }

    public function testComputeSharedXProducesThirtyTwoBytes(): void
    {
        $ecdh = new Secp256k1EcdhService();
        $signer = new Secp256k1SignatureService(null, new NativeRandomBytesGeneratorAdapter());

        $privateKey = PrivateKey::generate();
        $otherPublicKey = $signer->derivePublicKey(PrivateKey::generate());

        $sharedX = $ecdh->computeSharedX($privateKey, $otherPublicKey);

        $this->assertSame(32, strlen($sharedX));
    }
}
