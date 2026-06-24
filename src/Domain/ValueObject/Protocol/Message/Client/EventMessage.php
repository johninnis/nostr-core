<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Override;

final readonly class EventMessage extends ClientMessage
{
    protected const string TYPE = 'EVENT';

    public function __construct(private Event $event)
    {
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
        return [self::TYPE, $this->event->toArray()];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || self::TYPE !== $data[0]) {
            return null;
        }

        $event = Event::fromWire($data[1]);

        if (null === $event) {
            return null;
        }

        return new self($event->withRawJson());
    }
}
