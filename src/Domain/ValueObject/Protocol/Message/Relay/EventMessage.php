<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\PreSerialisedMessageInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class EventMessage extends RelayMessage implements PreSerialisedMessageInterface
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private Event $event,
    ) {
    }

    #[Override]
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

    #[Override]
    public function toArray(): array
    {
        return ['EVENT', (string) $this->subscriptionId, $this->event->toArray()];
    }

    #[Override]
    public function preSerialisedJson(): ?string
    {
        $rawJson = $this->event->getRawJson();

        if (null === $rawJson) {
            return null;
        }

        $subscriptionId = self::encode((string) $this->subscriptionId);

        return '["EVENT",'.$subscriptionId.','.$rawJson.']';
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (3 !== count($data) || 'EVENT' !== $data[0]) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        $event = Event::fromArray($data[2]);

        if (null === $event) {
            return null;
        }

        return new self(
            $subscriptionId,
            $event,
        );
    }
}
