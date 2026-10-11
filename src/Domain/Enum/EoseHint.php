<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum EoseHint: string
{
    case Finish = 'finish';
    case More = 'more';
    case Auth = 'auth';
}
