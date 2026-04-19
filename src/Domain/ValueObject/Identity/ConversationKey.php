<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;

final readonly class ConversationKey
{
    private const HEX_LENGTH = 64;

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
        if (!preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        $bytes = hex2bin($hex);
        assert(false !== $bytes);

        return new self(new SecretKeyMaterial($bytes));
    }

    public static function fromBytes(string $bytes): ?self
    {
        if (SecretKeyMaterial::BYTE_LENGTH !== strlen($bytes)) {
            return null;
        }

        return new self(new SecretKeyMaterial($bytes));
    }

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
