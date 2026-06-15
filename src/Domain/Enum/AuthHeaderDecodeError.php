<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum AuthHeaderDecodeError
{
    case TooLong;
    case BadFormat;
    case BadBase64;
    case BadJson;
    case InvalidEvent;
}
