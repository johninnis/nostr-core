<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum EventKindCategory
{
    case Regular;
    case Replaceable;
    case Ephemeral;
    case Addressable;
}
