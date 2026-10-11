<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;

enum Nip42ValidationFailure: string
{
    case WrongKind = 'wrong_kind';
    case ChallengeMismatch = 'challenge_mismatch';
    case ChallengeUnstated = 'challenge_unstated';
    case RelayMismatch = 'relay_mismatch';
    case TimestampOutsideTolerance = 'timestamp_outside_tolerance';

    public function message(): string
    {
        return match ($this) {
            self::WrongKind => 'Event must be kind 22242',
            self::ChallengeMismatch => 'Challenge does not match the one issued',
            self::ChallengeUnstated => 'Event must state exactly one challenge',
            self::RelayMismatch => 'Relay URL does not match this relay',
            self::TimestampOutsideTolerance => 'Event timestamp is outside tolerance',
        };
    }

    // Deliberate: only a replaced challenge is fixed by answering the current one, so every other failure is invalid: — see ADR-0127
    public function reasonPrefix(): ReasonPrefix
    {
        return self::ChallengeMismatch === $this ? ReasonPrefix::AuthRequired : ReasonPrefix::Invalid;
    }
}
