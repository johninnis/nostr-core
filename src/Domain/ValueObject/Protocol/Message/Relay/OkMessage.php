<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Override;

final readonly class OkMessage extends RelayMessage
{
    public function __construct(
        private EventId $eventId,
        private bool $accepted,
        private string $message = '',
    ) {
    }

    #[Override]
    public function type(): RelayMessageType
    {
        return RelayMessageType::Ok;
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

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, $this->eventId->toHex(), $this->accepted, $this->message];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function tryFromArray(array $data): ?static
    {
        if (!array_is_list($data) || count($data) < 3 || count($data) > 4) {
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

        $eventId = EventId::tryFromHex($data[1]);

        if (null === $eventId) {
            return null;
        }

        $parsed = new self(
            $eventId,
            $data[2],
            $message,
        );

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
