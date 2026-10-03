<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum SoleTagValueState: string
{
    case Absent = 'absent';
    case One = 'one';
    case Disagreeing = 'disagreeing';
}
