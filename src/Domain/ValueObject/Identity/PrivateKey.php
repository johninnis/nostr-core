<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Closure;
use Exception;
use Innis\Nostr\Core\Domain\Exception\InvalidSignatureException;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\SecretKeyMaterial;
use LogicException;
use Mdanter\Ecc\EccFactory;

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

        return new self(SecretKeyMaterial::fromBytes($bytes));
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
        return new self(SecretKeyMaterial::fromBytes($bytes));
    }

    public static function generate(): self
    {
        return new self(SecretKeyMaterial::random());
    }

    public function getPublicKey(): PublicKey
    {
        $publicKey = $this->material->expose(static function (string $privkeyBytes): PublicKey {
            if (Secp256k1::isAvailable()) {
                return PublicKey::fromHex(bin2hex(Secp256k1::derivePublicKey($privkeyBytes)))
                    ?? throw new LogicException('Key derivation produced invalid public key');
            }

            $adapter = EccFactory::getAdapter();
            $generator = EccFactory::getSecgCurves($adapter)->generator256k1();

            $privateKeyHex = bin2hex($privkeyBytes);
            try {
                $privateKeyInt = gmp_init($privateKeyHex, 16);
            } finally {
                sodium_memzero($privateKeyHex);
            }

            $publicKeyPoint = $generator->mul($privateKeyInt);

            if (0 !== gmp_cmp(gmp_mod($publicKeyPoint->getY(), 2), 0)) {
                $privateKeyInt = gmp_sub($generator->getOrder(), $privateKeyInt);
                $publicKeyPoint = $generator->mul($privateKeyInt);
            }

            $publicKeyHex = str_pad(gmp_strval($publicKeyPoint->getX(), 16), 64, '0', STR_PAD_LEFT);

            return PublicKey::fromHex($publicKeyHex)
                ?? throw new LogicException('Key derivation produced invalid public key');
        });
        assert($publicKey instanceof PublicKey);

        return $publicKey;
    }

    public function sign(string $messageBytes): Signature
    {
        $signature = $this->material->expose(function (string $privkeyBytes) use ($messageBytes): Signature {
            if (32 === strlen($messageBytes) && Secp256k1::isAvailable()) {
                return Signature::fromHex(bin2hex(Secp256k1::sign($messageBytes, $privkeyBytes)))
                    ?? throw new LogicException('FFI signing produced invalid signature');
            }

            $adapter = EccFactory::getAdapter();
            $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
            $n = $generator->getOrder();

            $privateKeyHex = bin2hex($privkeyBytes);
            try {
                $privateKeyInt = gmp_init($privateKeyHex, 16);
            } finally {
                sodium_memzero($privateKeyHex);
            }

            $P = $generator->mul($privateKeyInt);
            $d = 0 === gmp_cmp(gmp_mod($P->getY(), 2), 0) ? $privateKeyInt : gmp_sub($n, $privateKeyInt);

            $aux = random_bytes(32);
            $dBytes = SchnorrMathHelper::gmpToBytes($d, 32);
            $t = $this->xorBytes($dBytes, SchnorrMathHelper::taggedHash('BIP0340/aux', $aux));
            sodium_memzero($dBytes);

            $randInput = $t.SchnorrMathHelper::gmpToBytes($P->getX(), 32).$messageBytes;
            $rand = SchnorrMathHelper::taggedHash('BIP0340/nonce', $randInput);
            sodium_memzero($t);
            sodium_memzero($randInput);

            $randHex = bin2hex($rand);
            sodium_memzero($rand);
            try {
                $kPrime = gmp_mod(gmp_init($randHex, 16), $n);
            } finally {
                sodium_memzero($randHex);
            }

            if (0 === gmp_cmp($kPrime, 0)) {
                throw new InvalidSignatureException('BIP-340 nonce generation produced zero value');
            }

            $R = $generator->mul($kPrime);
            $k = 0 === gmp_cmp(gmp_mod($R->getY(), 2), 0) ? $kPrime : gmp_sub($n, $kPrime);

            $eInput = SchnorrMathHelper::gmpToBytes($R->getX(), 32).SchnorrMathHelper::gmpToBytes($P->getX(), 32).$messageBytes;
            $eHash = SchnorrMathHelper::taggedHash('BIP0340/challenge', $eInput);
            $e = gmp_mod(gmp_init(bin2hex($eHash), 16), $n);

            $s = gmp_mod(gmp_add($k, gmp_mul($e, $d)), $n);

            $rHex = str_pad(gmp_strval($R->getX(), 16), 64, '0', STR_PAD_LEFT);
            $sHex = str_pad(gmp_strval($s, 16), 64, '0', STR_PAD_LEFT);

            return Signature::fromHex($rHex.$sHex)
                ?? throw new LogicException('Schnorr signing produced invalid signature');
        });
        assert($signature instanceof Signature);

        return $signature;
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

    private function xorBytes(string $a, string $b): string
    {
        $result = '';
        $len = min(strlen($a), strlen($b));
        for ($i = 0; $i < $len; ++$i) {
            $result .= chr((ord($a[$i]) ^ ord($b[$i])) & 0xFF);
        }

        return $result;
    }
}
