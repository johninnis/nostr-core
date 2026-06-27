<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Time;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;

final class SystemClock implements ClockInterface
{
    // Deliberate: the injectable production clock; the Application layer depends on ClockInterface, never on this, and it returns the single 'now' source rather than reading the clock again — see ADR-0005
    #[Override]
    public function now(): Timestamp
    {
        return Timestamp::now();
    }
}
