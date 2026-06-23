<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum SubscriptionState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Live = 'live';
    case ClosedByRelay = 'closed_by_relay';
    case ClosedByClient = 'closed_by_client';

    public function isPending(): bool
    {
        return self::Pending === $this;
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Active, self::Live], true);
    }

    public function isReceivingEvents(): bool
    {
        return in_array($this, [self::Active, self::Live], true);
    }

    public function isLive(): bool
    {
        return self::Live === $this;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::ClosedByRelay, self::ClosedByClient], true);
    }
}
