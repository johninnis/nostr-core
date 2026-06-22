<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum Nip19EntityType: string
{
    case Pubkey = 'pubkey';
    case Event = 'event';
    case Profile = 'profile';
    case Address = 'address';
}
