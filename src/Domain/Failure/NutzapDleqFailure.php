<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum NutzapDleqFailure: string
{
    case NoProof = 'no_proof';
    case UnknownKeyset = 'unknown_keyset';
    case UnitMismatch = 'unit_mismatch';
    case NoMintKey = 'no_mint_key';
    case DleqInvalid = 'dleq_invalid';

    public function message(): string
    {
        return match ($this) {
            self::NoProof => 'The nutzap has no proof that states an amount',
            self::UnknownKeyset => 'A nutzap proof names no keyset its mint\'s keysets hold',
            self::UnitMismatch => 'A nutzap proof\'s keyset is in a unit other than the nutzap\'s',
            self::NoMintKey => 'A nutzap proof\'s keyset has no key for the proof\'s amount',
            self::DleqInvalid => 'A nutzap proof\'s DLEQ proof does not verify against its mint\'s key',
        };
    }
}
