<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;

final readonly class AuthMessage extends ClientMessage
{
    public function __construct(private Event $event)
    {
        if ($this->event->getKind()->toInt() !== EventKind::CLIENT_AUTH) {
            throw new \InvalidArgumentException('AUTH message must contain a kind 22242 event');
        }
    }

    public function getType(): string
    {
        return 'AUTH';
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function toArray(): array
    {
        return ['AUTH', $this->event->toArray()];
    }

    public static function fromArray(array $data): static
    {
        if (\count($data) !== 2 || $data[0] !== 'AUTH') {
            throw new \InvalidArgumentException('Invalid AUTH message format');
        }

        return new self(Event::fromArray($data[1]));
    }
}
