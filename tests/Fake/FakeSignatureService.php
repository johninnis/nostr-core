<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Fake;

use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use Override;
use RuntimeException;

final class FakeSignatureService implements SignatureServiceInterface
{
    private const string SIGNATURE_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private function __construct(private bool $verifies)
    {
    }

    public static function accepting(): self
    {
        return new self(true);
    }

    public static function rejecting(): self
    {
        return new self(false);
    }

    #[Override]
    public function sign(PrivateKey $privateKey, string $message): Signature
    {
        return Signature::fromHex(self::SIGNATURE_HEX) ?? throw new RuntimeException('Invalid fake signature');
    }

    #[Override]
    public function verify(PublicKey $publicKey, string $message, Signature $signature): bool
    {
        return $this->verifies;
    }

    #[Override]
    public function derivePublicKey(PrivateKey $privateKey): PublicKey
    {
        return KeyMother::alicePublicKey();
    }
}
