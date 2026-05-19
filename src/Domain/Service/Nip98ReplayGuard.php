<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;

final class Nip98ReplayGuard
{
    private array $seen = [];

    public function __construct(
        private readonly int $ttlSeconds = 120,
    ) {
    }

    public function recordOnce(EventId $eventId): bool
    {
        $now = time();
        $this->prune($now);

        $key = $eventId->toHex();
        if (isset($this->seen[$key])) {
            return false;
        }

        $this->seen[$key] = $now + $this->ttlSeconds;

        return true;
    }

    private function prune(int $now): void
    {
        foreach ($this->seen as $key => $expiresAt) {
            if ($expiresAt <= $now) {
                unset($this->seen[$key]);
            }
        }
    }
}
