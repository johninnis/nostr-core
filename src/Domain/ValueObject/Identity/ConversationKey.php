<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use SensitiveParameter;

final readonly class ConversationKey
{
    private function __construct(private SecretKeyMaterial $material)
    {
    }

    // Deliberate: a peer key the key agreement refuses is NIP-44's own failure, the refusal kept as its cause — see ADR-0089
    public static function derive(PrivateKey $privateKey, PublicKey $publicKey, EcdhServiceInterface $ecdhService): self
    {
        try {
            $sharedX = $ecdhService->computeSharedX($privateKey, $publicKey);
        } catch (EcdhException $refusal) {
            throw new EncryptionException($refusal->getMessage(), previous: $refusal);
        }
        $conversationKey = $sharedX->expose(static fn (string $sharedXBytes): string => hash_hmac('sha256', $sharedXBytes, 'nip44-v2', true));

        $sharedX->zero();

        return new self(SecretKeyMaterial::fromBytes($conversationKey));
    }

    public static function tryFromHex(#[SensitiveParameter] string $hex): ?self
    {
        $material = SecretKeyMaterial::tryFromHex($hex);

        return null === $material ? null : new self($material);
    }

    public static function tryFromBytes(#[SensitiveParameter] string $bytes): ?self
    {
        $material = SecretKeyMaterial::tryFromBytes($bytes);

        return null === $material ? null : new self($material);
    }

    /**
     * @template T
     *
     * @param Closure(string): T $fn
     *
     * @return T
     */
    public function expose(Closure $fn): mixed
    {
        return $this->material->expose($fn);
    }

    public function zero(): void
    {
        $this->material->zero();
    }

    public function isZeroed(): bool
    {
        return $this->material->isZeroed();
    }
}
