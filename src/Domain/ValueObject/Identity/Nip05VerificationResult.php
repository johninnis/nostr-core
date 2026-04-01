<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

final readonly class Nip05VerificationResult
{
    private function __construct(
        private Nip05Identifier $identifier,
        private PublicKey $pubkey,
        private bool $isValid,
        private ?string $errorReason = null,
    ) {
    }

    public static function success(Nip05Identifier $identifier, PublicKey $pubkey): self
    {
        return new self($identifier, $pubkey, true);
    }

    public static function failure(
        Nip05Identifier $identifier,
        PublicKey $pubkey,
        string $reason,
    ): self {
        return new self($identifier, $pubkey, false, $reason);
    }

    public function getIdentifier(): Nip05Identifier
    {
        return $this->identifier;
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrorReason(): ?string
    {
        return $this->errorReason;
    }
}
