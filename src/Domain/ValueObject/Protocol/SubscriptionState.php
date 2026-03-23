<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

enum SubscriptionState: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case LIVE = 'live';
    case CLOSED_BY_RELAY = 'closed_by_relay';
    case CLOSED_BY_CLIENT = 'closed_by_client';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isOpen(): bool
    {
        return \in_array($this, [self::PENDING, self::ACTIVE, self::LIVE], true);
    }

    public function isReceivingEvents(): bool
    {
        return \in_array($this, [self::ACTIVE, self::LIVE], true);
    }

    public function isLive(): bool
    {
        return $this === self::LIVE;
    }

    public function isTerminal(): bool
    {
        return \in_array($this, [self::CLOSED_BY_RELAY, self::CLOSED_BY_CLIENT], true);
    }
}
