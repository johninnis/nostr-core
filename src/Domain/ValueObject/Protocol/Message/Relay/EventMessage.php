<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;

final readonly class EventMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private Event $event,
    ) {
    }

    public function getType(): string
    {
        return 'EVENT';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function toArray(): array
    {
        return ['EVENT', (string) $this->subscriptionId, $this->event->toArray()];
    }

    public static function fromArray(array $data): static
    {
        if (3 !== count($data) || 'EVENT' !== $data[0]) {
            throw new InvalidArgumentException('Invalid EVENT message format');
        }

        return new self(
            SubscriptionId::fromString($data[1]),
            Event::fromArray($data[2])
        );
    }
}
