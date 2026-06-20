<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum Nip10Marker: string
{
    case Root = 'root';
    case Reply = 'reply';
    case Mention = 'mention';
}
