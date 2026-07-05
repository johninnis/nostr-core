<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;

// Deliberate: a downstream-implemented port with no in-package implementer or consumer; zero in-package references is expected, not dead code — see ADR-0032
interface EventHandlerInterface
{
    public function handleEvent(Event $event, SubscriptionId $subscriptionId): void;

    public function handleEose(SubscriptionId $subscriptionId): void;

    public function handleClosed(SubscriptionId $subscriptionId, string $message): void;

    public function handleNotice(RelayUrl $relayUrl, string $message): void;
}
