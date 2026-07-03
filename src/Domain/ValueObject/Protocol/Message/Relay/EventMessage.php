<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
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
    public function type(): RelayMessageType
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

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, (string) $this->subscriptionId, $this->event->toArray()];
    }

    #[Override]
    public function preSerialisedJson(): ?string
    {
        $rawJson = $this->event->getRawJson();

        if (null === $rawJson) {
            return null;
        }

        $subscriptionId = self::encode((string) $this->subscriptionId);

        return '["'.$this->type()->value.'",'.$subscriptionId.','.$rawJson.']';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function tryFromArray(array $data): ?static
    {
        if (3 !== count($data)) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        $event = Event::tryFromArray($data[2]);

        if (null === $event) {
            return null;
        }

        $parsed = new self(
            $subscriptionId,
            $event,
        );

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
