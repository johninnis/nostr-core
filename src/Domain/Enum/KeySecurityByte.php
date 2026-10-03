<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum KeySecurityByte: int
{
    case KnownInsecure = 0x00;
    case NotKnownInsecure = 0x01;
    case Untracked = 0x02;
}
