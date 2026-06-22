<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

use InvalidArgumentException;

enum KeySecurityByte: int
{
    case ClientSideOnly = 0x00;
    case UsableUntrusted = 0x01;
    case Unknown = 0x02;

    // Deliberate: throws on an out-of-range byte rather than mapping to Unknown, so a tampered ncryptsec is rejected at decrypt — see ADR-0009
    public static function fromByte(int $byte): self
    {
        return self::tryFrom($byte)
            ?? throw new InvalidArgumentException(sprintf('Unknown key security byte: 0x%02x', $byte));
    }
}
