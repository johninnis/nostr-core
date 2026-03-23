<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;

final readonly class EventMessage extends ClientMessage
{
    public function __construct(private Event $event)
    {
    }

    public function getType(): string
    {
        return 'EVENT';
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function toArray(): array
    {
        return ['EVENT', $this->event->toArray()];
    }

    public static function fromArray(array $data): static
    {
        if (\count($data) !== 2 || $data[0] !== 'EVENT') {
            throw new \InvalidArgumentException('Invalid EVENT message format');
        }

        return new self(Event::fromArray($data[1]));
    }
}
