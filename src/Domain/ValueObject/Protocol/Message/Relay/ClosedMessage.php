<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

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

    #[Override]
    public function toArray(): array
    {
        return ['CLOSED', (string) $this->subscriptionId, $this->message];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (count($data) < 2 || 'CLOSED' !== $data[0]) {
            return null;
        }

        if (!is_string($data[1])) {
            return null;
        }

        $message = $data[2] ?? '';
        if (!is_string($message)) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromString($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        return new self(
            $subscriptionId,
            $message,
        );
    }
}
