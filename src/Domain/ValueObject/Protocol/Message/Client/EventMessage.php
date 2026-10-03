<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Override;

final readonly class EventMessage extends ClientMessage
{
    public function __construct(private Event $event)
    {
    }

    #[Override]
    public static function type(): ClientMessageType
    {
        return ClientMessageType::Event;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    #[Override]
    protected function toPayload(): array
    {
        return [$this->event->toArray()];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if (1 !== count($payload)) {
            return null;
        }

        $event = Event::tryFromArray($payload[0]);

        return null === $event ? null : new self($event);
    }
}
