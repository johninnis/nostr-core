<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

enum Secp256k1Backend
{
    case Native;
    case PurePhp;
}
