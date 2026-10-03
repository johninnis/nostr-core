<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum RelayMarker: string
{
    case Read = 'read';
    case Write = 'write';
    case Both = 'both';

    // Deliberate: NIP-65 says an r tag without a marker is both read and write, and a marker NIP-65 does not define is read the same way rather than dropping the relay — see ADR-0132
    public static function fromTagValue(?string $value): self
    {
        return match ($value) {
            'read' => self::Read,
            'write' => self::Write,
            default => self::Both,
        };
    }
}
