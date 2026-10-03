<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Override;

final readonly class OkMessage extends RelayMessage
{
    private function __construct(
        private EventId $eventId,
        private bool $accepted,
        private string $message,
    ) {
    }

    public static function accepted(EventId $eventId, string $message = ''): self
    {
        return new self($eventId, true, $message);
    }

    public static function refused(EventId $eventId, ReasonPrefix $prefix, string $detail): self
    {
        return new self($eventId, false, $prefix->format($detail));
    }

    #[Override]
    public static function type(): RelayMessageType
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

    // Deliberate: parsed from the message on demand, a refusal always has one, and the only per-prefix predicate is the one a resend loop asks — see ADR-0087
    public function getReasonPrefix(): ?ReasonPrefix
    {
        return $this->accepted ? ReasonPrefix::tryFromMessage($this->message) : ReasonPrefix::ofRefusal($this->message);
    }

    public function isAuthRequired(): bool
    {
        return !$this->accepted && ReasonPrefix::AuthRequired === $this->getReasonPrefix();
    }

    #[Override]
    protected function toPayload(): array
    {
        return [$this->eventId->toHex(), $this->accepted, $this->message];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if (count($payload) < 3) {
            return null;
        }

        [$eventIdHex, $accepted, $message] = $payload;
        $eventId = is_string($eventIdHex) ? EventId::tryFromHex($eventIdHex) : null;

        return null === $eventId || !is_bool($accepted) || !is_string($message) ? null : new self($eventId, $accepted, $message);
    }
}
