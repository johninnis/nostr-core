<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum CommentScope: string
{
    case Event = 'event';
    case Address = 'address';
    case External = 'external';
}
