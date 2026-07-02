<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class ClosedMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private string $message,
    ) {
    }

    #[Override]
    public function type(): RelayMessageType
    {
        return RelayMessageType::Closed;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, (string) $this->subscriptionId, $this->message];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (count($data) < 2) {
            return null;
        }

        $message = $data[2] ?? '';
        if (!is_string($message)) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        $parsed = new self(
            $subscriptionId,
            $message,
        );

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
