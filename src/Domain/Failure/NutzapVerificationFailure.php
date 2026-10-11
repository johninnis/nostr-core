<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum NutzapVerificationFailure: string
{
    case RecipientMismatch = 'recipient_mismatch';
    case MintNotListed = 'mint_not_listed';
    case NoProof = 'no_proof';
    case ProofNotLockedToRecipient = 'proof_not_locked_to_recipient';

    public function message(): string
    {
        return match ($this) {
            self::RecipientMismatch => 'The nutzap info is not by the nutzap\'s one recipient',
            self::MintNotListed => 'The nutzap names no mint its recipient\'s nutzap info lists',
            self::NoProof => 'The nutzap has no proof that states an amount',
            self::ProofNotLockedToRecipient => 'A nutzap proof is not locked to the recipient\'s P2PK key alone',
        };
    }
}
