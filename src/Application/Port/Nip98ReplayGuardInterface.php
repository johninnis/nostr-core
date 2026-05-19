<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;

interface Nip98ReplayGuardInterface
{
    public function recordOnce(EventId $eventId, int $ttlSeconds): bool;
}
