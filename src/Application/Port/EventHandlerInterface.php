<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;

// Deliberate: a published port implemented and called only by downstream connection-driving consumers; it has no in-package implementer or consumer by design — do not delete as "orphaned" — see ADR-0032
interface EventHandlerInterface
{
    public function handleEvent(Event $event, SubscriptionId $subscriptionId): void;

    public function handleEose(SubscriptionId $subscriptionId): void;

    public function handleClosed(SubscriptionId $subscriptionId, string $message): void;

    public function handleNotice(RelayUrl $relayUrl, string $message): void;
}
