<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum RumourParseFailure: string
{
    case Malformed = 'malformed';
    case IdMismatch = 'id_mismatch';

    public function message(): string
    {
        return match ($this) {
            self::Malformed => 'Value is not a well-formed unsigned event',
            self::IdMismatch => 'Stated id does not match the id computed from the rumour fields',
        };
    }
}
