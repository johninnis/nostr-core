<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Exception;
use Innis\Nostr\Core\Domain\Exception\InvalidSignatureException;
use LogicException;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Random\RandomGeneratorFactory;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use RuntimeException;

final readonly class PrivateKey
{
    public const HEX_LENGTH = 64;

    private function __construct(private string $key)
    {
    }

    public function getPublicKey(): PublicKey
    {
        if (Secp256k1::isAvailable()) {
            $privkeyBytes = hex2bin($this->key);
            if (false === $privkeyBytes) {
                throw new RuntimeException('Failed to decode private key hex');
            }

            return PublicKey::fromHex(bin2hex(Secp256k1::derivePublicKey($privkeyBytes)))
                ?? throw new LogicException('Key derivation produced invalid public key');
        }

        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $privateKeyInt = gmp_init($this->key, 16);
        $publicKeyPoint = $generator->mul($privateKeyInt);

        if (0 !== gmp_cmp(gmp_mod($publicKeyPoint->getY(), 2), 0)) {
            $privateKeyInt = gmp_sub($generator->getOrder(), $privateKeyInt);
            $publicKeyPoint = $generator->mul($privateKeyInt);
        }

        $publicKeyHex = str_pad(gmp_strval($publicKeyPoint->getX(), 16), 64, '0', STR_PAD_LEFT);

        return PublicKey::fromHex($publicKeyHex)
            ?? throw new LogicException('Key derivation produced invalid public key');
    }

    public function sign(string $messageBytes): Signature
    {
        if (32 === strlen($messageBytes) && Secp256k1::isAvailable()) {
            $privkeyBytes = hex2bin($this->key);
            if (false === $privkeyBytes) {
                throw new InvalidSignatureException('Failed to decode private key hex');
            }

            return Signature::fromHex(bin2hex(Secp256k1::sign($messageBytes, $privkeyBytes)))
                ?? throw new LogicException('FFI signing produced invalid signature');
        }

        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $curve = EccFactory::getSecgCurves($adapter)->curve256k1();
        $privateKeyInt = gmp_init($this->key, 16);
        $n = $generator->getOrder();

        $P = $generator->mul($privateKeyInt);
        $d = 0 === gmp_cmp(gmp_mod($P->getY(), 2), 0) ? $privateKeyInt : gmp_sub($n, $privateKeyInt);

        $aux = random_bytes(32);
        $t = $this->xorBytes(SchnorrMathHelper::gmpToBytes($d, 32), SchnorrMathHelper::taggedHash('BIP0340/aux', $aux));

        $randInput = $t.SchnorrMathHelper::gmpToBytes($P->getX(), 32).$messageBytes;
        $rand = SchnorrMathHelper::taggedHash('BIP0340/nonce', $randInput);

        $kPrime = gmp_mod(gmp_init(bin2hex($rand), 16), $n);
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

    public function toHex(): string
    {
        return $this->key;
    }

    public function toBech32(): string
    {
        return Bech32Codec::encode('nsec', Bech32Codec::hexToBytes($this->key));
    }

    public static function generate(): self
    {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves($adapter)->generator256k1();
        $randomGen = RandomGeneratorFactory::getRandomGenerator();
        $privateKeyInt = $randomGen->generate($generator->getOrder());
        $hex = str_pad(gmp_strval($privateKeyInt, 16), 64, '0', STR_PAD_LEFT);

        return new self($hex);
    }

    public static function fromHex(string $hex): ?self
    {
        if (!preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        return new self($hex);
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
}
