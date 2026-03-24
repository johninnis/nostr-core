<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;

final readonly class OkMessage extends RelayMessage
{
    public function __construct(
        private EventId $eventId,
        private bool $accepted,
        private string $message = '',
    ) {
    }

    public function getType(): string
    {
        return 'OK';
    }

    public function getEventId(): EventId
    {
        return $this->eventId;
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return ['OK', $this->eventId->toHex(), $this->accepted, $this->message];
    }

    public static function fromArray(array $data): static
    {
        if (count($data) < 3 || 'OK' !== $data[0]) {
            throw new InvalidArgumentException('Invalid OK message format');
        }

        return new self(
            EventId::fromHex($data[1]) ?? throw new InvalidArgumentException('Invalid event ID in OK message'),
            (bool) $data[2],
            $data[3] ?? ''
        );
    }
}
