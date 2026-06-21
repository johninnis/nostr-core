<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;

final readonly class ConversationKey
{
    private function __construct(private SecretKeyMaterial $material)
    {
    }

    public static function derive(PrivateKey $privateKey, PublicKey $publicKey, EcdhServiceInterface $ecdhService): self
    {
        $sharedX = $ecdhService->computeSharedX($privateKey, $publicKey);
        $conversationKey = hash_hmac('sha256', $sharedX, 'nip44-v2', true);

        sodium_memzero($sharedX);

        return new self(new SecretKeyMaterial($conversationKey));
    }

    public static function fromHex(string $hex): ?self
    {
        $material = SecretKeyMaterial::fromHex($hex);

        return null === $material ? null : new self($material);
    }

    public static function fromBytes(string $bytes): ?self
    {
        $material = SecretKeyMaterial::fromBytes($bytes);

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
