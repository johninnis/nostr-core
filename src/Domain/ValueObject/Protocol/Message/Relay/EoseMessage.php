<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class EoseMessage extends RelayMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    #[Override]
    public function getType(): string
    {
        return 'EOSE';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    #[Override]
    public function toArray(): array
    {
        return ['EOSE', (string) $this->subscriptionId];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || 'EOSE' !== $data[0] || !is_string($data[1])) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromString($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        return new self($subscriptionId);
    }
}
