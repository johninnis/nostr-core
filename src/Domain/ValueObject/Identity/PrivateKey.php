<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\HexCodec;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;

final readonly class PrivateKey
{
    private function __construct(private SecretKeyMaterial $material)
    {
    }

    public static function fromHex(string $hex): ?self
    {
        if (!HexCodec::isValid($hex, SecretKeyMaterial::BYTE_LENGTH)) {
            return null;
        }

        return new self(new SecretKeyMaterial(HexCodec::toBytes($hex)));
    }

    public static function fromBech32(string $bech32): ?self
    {
        $decoded = Bech32Codec::decode($bech32);
        if (null === $decoded || 'nsec' !== $decoded['hrp']) {
            return null;
        }

        return self::fromBytes($decoded['data']);
    }

    public static function fromBytes(string $bytes): ?self
    {
        if (SecretKeyMaterial::BYTE_LENGTH !== strlen($bytes)) {
            return null;
        }

        return new self(new SecretKeyMaterial($bytes));
    }

    public static function generate(): self
    {
        return new self(SecretKeyMaterial::random());
    }

    public function toHex(): string
    {
        return $this->material->expose(HexCodec::fromBytes(...));
    }

    public function toBech32(): string
    {
        return $this->material->expose(static fn (string $bytes): string => Bech32Codec::encode('nsec', $bytes));
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
