<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class EoseMessage extends RelayMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Eose;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    #[Override]
    protected function toPayload(): array
    {
        return [(string) $this->subscriptionId];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if ([] === $payload) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($payload[0]);

        return null === $subscriptionId ? null : new self($subscriptionId);
    }
}
