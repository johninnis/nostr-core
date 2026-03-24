<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;

final readonly class ClosedMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private string $message,
    ) {
    }

    public function getType(): string
    {
        return 'CLOSED';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return ['CLOSED', (string) $this->subscriptionId, $this->message];
    }

    public static function fromArray(array $data): static
    {
        if (count($data) < 2 || 'CLOSED' !== $data[0]) {
            throw new InvalidArgumentException('Invalid CLOSED message format');
        }

        return new self(
            SubscriptionId::fromString($data[1]),
            $data[2] ?? ''
        );
    }
}
