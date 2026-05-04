<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum Bech32Variant: int
{
    case Bech32 = 1;
    case Bech32m = 0x2BC830A3;
}
