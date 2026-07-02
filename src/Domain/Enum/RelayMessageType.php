<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum RelayMessageType: string
{
    case Event = 'EVENT';
    case Ok = 'OK';
    case Eose = 'EOSE';
    case Closed = 'CLOSED';
    case Notice = 'NOTICE';
    case Auth = 'AUTH';
    case Count = 'COUNT';
}
