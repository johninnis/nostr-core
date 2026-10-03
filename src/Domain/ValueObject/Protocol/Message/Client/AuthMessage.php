<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use InvalidArgumentException;
use Override;

final readonly class AuthMessage extends ClientMessage
{
    private function __construct(private Event $event)
    {
    }

    public static function tryFromEvent(Event $event): ?self
    {
        return $event->getKind()->is(EventKind::CLIENT_AUTH) ? new self($event) : null;
    }

    public static function fromEvent(Event $event): self
    {
        return self::tryFromEvent($event) ?? throw new InvalidArgumentException('AUTH message must contain a kind 22242 event');
    }

    #[Override]
    public static function type(): ClientMessageType
    {
        return ClientMessageType::Auth;
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

        return null === $event ? null : self::tryFromEvent($event);
    }
}
