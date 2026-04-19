<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Exception;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;

final readonly class PrivateKey
{
    public const HEX_LENGTH = 64;

    private function __construct(private SecretKeyMaterial $material)
    {
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

    public static function fromBech32(string $bech32): ?self
    {
        if (!str_starts_with($bech32, 'nsec1')) {
            return null;
        }

        try {
            $decoded = Bech32Codec::decode($bech32);

            return self::fromHex(Bech32Codec::bytesToHex($decoded['data']));
        } catch (Exception) {
            return null;
        }
    }

    public static function fromBytes(string $bytes): self
    {
        return new self(new SecretKeyMaterial($bytes));
    }

    public static function generate(): self
    {
        return new self(SecretKeyMaterial::random());
    }

    public function toHex(): string
    {
        $hex = $this->material->expose(static fn (string $bytes): string => bin2hex($bytes));
        assert(is_string($hex));

        return $hex;
    }

    public function toBech32(): string
    {
        $bech32 = $this->material->expose(static function (string $bytes): string {
            $byteValues = unpack('C*', $bytes);
            assert(false !== $byteValues);

            return Bech32Codec::encode('nsec', array_values($byteValues));
        });
        assert(is_string($bech32));

        return $bech32;
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
