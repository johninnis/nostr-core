<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Time;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;

final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): Timestamp
    {
        return Timestamp::fromInt(time());
    }
}
