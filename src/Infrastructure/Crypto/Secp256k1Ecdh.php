<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use GMP;
use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\SecretKeyMaterial;
use Override;

final readonly class Secp256k1Ecdh implements EcdhServiceInterface
{
    public function __construct(private ?LibSecp256k1Ffi $ffi)
    {
    }

    public static function create(RandomBytesGeneratorInterface $randomBytes = new NativeRandomBytesGenerator()): self
    {
        return new self(LibSecp256k1Ffi::tryLoad($randomBytes));
    }

    // Deliberate: deployment introspection only, and deliberately absent from EcdhServiceInterface so no domain or application code can branch on the backend — see ADR-0101
    public function backend(): Secp256k1Backend
    {
        return null !== $this->ffi ? Secp256k1Backend::Native : Secp256k1Backend::PurePhp;
    }

    #[Override]
    public function computeSharedX(PrivateKey $privateKey, PublicKey $publicKey): SecretKeyMaterial
    {
        $publicKeyX = gmp_import($publicKey->toBytes());
        // Deliberate: this gmp check runs ahead of the native/FFI dispatch below; gmp is a hard dependency (paragonie/ecc requires it), so there is no FFI-without-gmp host to keep it free of — do not "tidy" it back to a gmp-free string comparison — see ADR-0101
        if (!Secp256k1Math::isXCoordinateInField($publicKeyX)) {
            throw new EcdhException('ECDH public key x-coordinate out of field range');
        }

        // Deliberate: native libsecp256k1 when present, pure-PHP fallback otherwise; both pinned by the ECDH parity suite — see ADR-0101
        return null !== $this->ffi
            ? self::computeSharedXNative($this->ffi, $privateKey, $publicKey)
            : self::computeSharedXPurePhp($privateKey, $publicKeyX);
    }

    private static function computeSharedXNative(LibSecp256k1Ffi $ffi, PrivateKey $privateKey, PublicKey $publicKey): SecretKeyMaterial
    {
        $publicKeyBytes = $publicKey->toBytes();

        return $privateKey->expose(static fn (string $privkeyBytes): SecretKeyMaterial => SecretKeyMaterial::fromBytes($ffi->computeSharedX($privkeyBytes, $publicKeyBytes)));
    }

    private static function computeSharedXPurePhp(PrivateKey $privateKey, GMP $publicKeyX): SecretKeyMaterial
    {
        $publicKeyPoint = Secp256k1Math::liftX($publicKeyX) ?? throw new EcdhException('ECDH public key is not a valid curve point');

        return $privateKey->expose(static function (string $privkeyBytes) use ($publicKeyPoint): SecretKeyMaterial {
            $sharedPoint = $publicKeyPoint->mul(gmp_import($privkeyBytes));
            if ($sharedPoint->isInfinity()) {
                throw new EcdhException('ECDH shared point is the identity');
            }

            return SecretKeyMaterial::fromBytes(Secp256k1Math::gmpToBytes($sharedPoint->getX(), SecretKeyMaterial::BYTE_LENGTH));
        });
    }
}
