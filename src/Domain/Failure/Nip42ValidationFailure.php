<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum Nip42ValidationFailure: string
{
    case WrongKind = 'wrong_kind';
    case ChallengeMismatch = 'challenge_mismatch';
    case RelayMismatch = 'relay_mismatch';
    case TimestampOutsideTolerance = 'timestamp_outside_tolerance';

    public function message(): string
    {
        return match ($this) {
            self::WrongKind => 'Event must be kind 22242',
            self::ChallengeMismatch => 'Challenge does not match the one issued',
            self::RelayMismatch => 'Relay URL does not match this relay',
            self::TimestampOutsideTolerance => 'Event timestamp is outside tolerance',
        };
    }
}
