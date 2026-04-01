<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use LogicException;
use Mdanter\Ecc\EccFactory;

final readonly class ConversationKey
{
    public const HEX_LENGTH = 64;

    private function __construct(private string $key)
    {
    }

    public static function derive(PrivateKey $privateKey, PublicKey $publicKey): self
    {
        $sharedX = self::computeSharedX($privateKey, $publicKey);
        $conversationKey = hash_hmac('sha256', $sharedX, 'nip44-v2', true);

        return new self(bin2hex($conversationKey));
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }

    public static function fromBytes(string $bytes): ?self
    {
        if (32 !== strlen($bytes)) {
            return null;
        }

        return new self(bin2hex($bytes));
    }

    public function toHex(): string
    {
        return $this->key;
    }

    public function toBytes(): string
    {
        $bytes = hex2bin($this->key);
        if (false === $bytes) {
            throw new LogicException('Invalid hex in conversation key');
        }

        return $bytes;
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public function __toString(): string
    {
        return $this->key;
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
