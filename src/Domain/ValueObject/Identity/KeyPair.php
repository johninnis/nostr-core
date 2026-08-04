<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;

// Deliberate: the constructor is private so a pair can only exist with its public key derived from its private key; a caller-supplied pair could disagree, and `Rumour::sign` would then mint an event whose signature does not verify against its own pubkey — see ADR-0061
final readonly class KeyPair
{
    private function __construct(
        private PrivateKey $privateKey,
        private PublicKey $publicKey,
    ) {
    }

    public function getPrivateKey(): PrivateKey
    {
        return $this->privateKey;
    }

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }

    public static function generate(SignatureServiceInterface $signatureService): self
    {
        $privateKey = PrivateKey::generate();
        $publicKey = $signatureService->derivePublicKey($privateKey);

        return new self($privateKey, $publicKey);
    }

    public static function fromPrivateKey(PrivateKey $privateKey, SignatureServiceInterface $signatureService): self
    {
        $publicKey = $signatureService->derivePublicKey($privateKey);

        return new self($privateKey, $publicKey);
    }
}
