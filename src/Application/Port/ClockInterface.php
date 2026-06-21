<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

interface ClockInterface
{
    public function now(): Timestamp;
}
