<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class EventMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private Event $event,
    ) {
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Event;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    #[Override]
    protected function toPayload(): array
    {
        return [(string) $this->subscriptionId, $this->event->toArray()];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if (count($payload) < 2) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($payload[0]);
        $event = Event::tryFromArray($payload[1]);

        return null === $subscriptionId || null === $event ? null : new self($subscriptionId, $event);
    }
}
