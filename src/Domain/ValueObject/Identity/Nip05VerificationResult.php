<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailureReason;

final readonly class Nip05VerificationResult
{
    private function __construct(
        private Nip05Identifier $identifier,
        private PublicKey $pubkey,
        private ?Nip05VerificationFailureReason $failureReason,
    ) {
    }

    public static function success(Nip05Identifier $identifier, PublicKey $pubkey): self
    {
        return new self($identifier, $pubkey, null);
    }

    public static function failure(
        Nip05Identifier $identifier,
        PublicKey $pubkey,
        Nip05VerificationFailureReason $reason,
    ): self {
        return new self($identifier, $pubkey, $reason);
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
        return null === $this->failureReason;
    }

    public function getFailureReason(): ?Nip05VerificationFailureReason
    {
        return $this->failureReason;
    }
}
