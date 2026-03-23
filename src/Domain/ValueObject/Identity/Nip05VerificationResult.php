<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

final readonly class Nip05VerificationResult
{
    public function __construct(
        public Nip05Identifier $identifier,
        public PublicKey $pubkey,
        public bool $isValid,
        public ?string $errorReason = null,
    ) {
    }

    public static function success(Nip05Identifier $identifier, PublicKey $pubkey): self
    {
        return new self($identifier, $pubkey, true);
    }

    public static function failure(
        Nip05Identifier $identifier,
        PublicKey $pubkey,
        string $reason
    ): self {
        return new self($identifier, $pubkey, false, $reason);
    }
}
