<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum ClientMessageType: string
{
    case Event = 'EVENT';
    case Req = 'REQ';
    case Close = 'CLOSE';
    case Auth = 'AUTH';
    case Count = 'COUNT';
}
