<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Override;

final readonly class OkMessage extends RelayMessage
{
    protected const string TYPE = 'OK';

    public function __construct(
        private EventId $eventId,
        private bool $accepted,
        private string $message = '',
    ) {
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

    public function isAuthRequired(): bool
    {
        return !$this->accepted && str_starts_with($this->message, 'auth-required:');
    }

    #[Override]
    public function toArray(): array
    {
        return [self::TYPE, $this->eventId->toHex(), $this->accepted, $this->message];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (count($data) < 3 || self::TYPE !== $data[0]) {
            return null;
        }

        if (!is_string($data[1])) {
            return null;
        }

        if (!is_bool($data[2])) {
            return null;
        }

        $message = $data[3] ?? '';
        if (!is_string($message)) {
            return null;
        }

        $eventId = EventId::fromHex($data[1]);

        if (null === $eventId) {
            return null;
        }

        return new self(
            $eventId,
            $data[2],
            $message,
        );
    }
}
