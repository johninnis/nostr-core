<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

interface Nip11ServiceInterface
{
    public function fetchNip11Info(RelayUrl $relayUrl): ?Nip11Info;
}
