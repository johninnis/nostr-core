<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum AuthHeaderDecodeFailure
{
    case TooLong;
    case BadFormat;
    case BadBase64;
    case BadJson;
    case InvalidEvent;
}
