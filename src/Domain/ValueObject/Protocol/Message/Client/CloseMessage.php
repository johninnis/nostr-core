<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class CloseMessage extends ClientMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    #[Override]
    public function getType(): string
    {
        return 'CLOSE';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    #[Override]
    public function toArray(): array
    {
        return ['CLOSE', (string) $this->subscriptionId];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || 'CLOSE' !== $data[0]) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        return new self($subscriptionId);
    }
}
