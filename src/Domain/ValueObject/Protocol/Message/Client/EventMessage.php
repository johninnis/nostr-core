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
    public function type(): ClientMessageType
    {
        return ClientMessageType::Event;
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
        return [$this->type()->value, $this->event->toArray()];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function tryFromArray(array $data): ?static
    {
        if (!array_is_list($data) || 2 !== count($data)) {
            return null;
        }

        $event = Event::tryFromArray($data[1]);

        if (null === $event) {
            return null;
        }

        $parsed = new self($event->withRawJson());

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
