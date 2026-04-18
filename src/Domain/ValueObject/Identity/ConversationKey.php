<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;
use LogicException;
use Mdanter\Ecc\EccFactory;

final readonly class ConversationKey
{
    private const HEX_LENGTH = 64;

    private function __construct(private SecretKeyMaterial $material)
    {
    }

    public static function derive(PrivateKey $privateKey, PublicKey $publicKey): self
    {
        $sharedX = self::computeSharedX($privateKey, $publicKey);
        $conversationKey = hash_hmac('sha256', $sharedX, 'nip44-v2', true);

        sodium_memzero($sharedX);

        return new self(SecretKeyMaterial::fromBytes($conversationKey));
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        $bytes = hex2bin($hex);
        assert(false !== $bytes);

        return new self(SecretKeyMaterial::fromBytes($bytes));
    }

    public static function fromBytes(string $bytes): ?self
    {
        if (SecretKeyMaterial::BYTE_LENGTH !== strlen($bytes)) {
            return null;
        }

        return new self(SecretKeyMaterial::fromBytes($bytes));
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

    private static function computeSharedX(PrivateKey $privateKey, PublicKey $publicKey): string
    {
        $adapter = EccFactory::getAdapter();
        $curve = EccFactory::getSecgCurves($adapter)->curve256k1();

        $privateKeyInt = gmp_init($privateKey->toHex(), 16);
        $publicKeyX = gmp_init($publicKey->toHex(), 16);
        $publicKeyY = $curve->recoverYfromX(false, $publicKeyX);

        $publicKeyPoint = $curve->getPoint($publicKeyX, $publicKeyY);
        $sharedPoint = $publicKeyPoint->mul($privateKeyInt);

        $sharedXBytes = hex2bin(str_pad(gmp_strval($sharedPoint->getX(), 16), 64, '0', STR_PAD_LEFT));
        if (false === $sharedXBytes) {
            throw new LogicException('ECDH produced invalid shared secret');
        }

        return $sharedXBytes;
    }
}
