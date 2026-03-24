<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use InvalidArgumentException;

final readonly class KeyPair
{
    public function __construct(
        private PrivateKey $privateKey,
        private PublicKey $publicKey,
    ) {
        if (!$this->privateKey->getPublicKey()->equals($this->publicKey)) {
            throw new InvalidArgumentException('Private key does not match public key');
        }
    }

    public function getPrivateKey(): PrivateKey
    {
        return $this->privateKey;
    }

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }

    public function sign(string $message): Signature
    {
        return $this->privateKey->sign($message);
    }

    public function verify(string $message, Signature $signature): bool
    {
        return $this->publicKey->verify($message, $signature);
    }

    public static function generate(): self
    {
        $privateKey = PrivateKey::generate();
        $publicKey = $privateKey->getPublicKey();

        return new self($privateKey, $publicKey);
    }

    public static function fromPrivateKey(PrivateKey $privateKey): self
    {
        $publicKey = $privateKey->getPublicKey();

        return new self($privateKey, $publicKey);
    }
}
