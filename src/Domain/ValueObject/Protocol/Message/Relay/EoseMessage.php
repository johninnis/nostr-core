<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;

final readonly class EoseMessage extends RelayMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    public function getType(): string
    {
        return 'EOSE';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function toArray(): array
    {
        return ['EOSE', (string) $this->subscriptionId];
    }

    public static function fromArray(array $data): static
    {
        if (2 !== count($data) || 'EOSE' !== $data[0]) {
            throw new InvalidArgumentException('Invalid EOSE message format');
        }

        return new self(SubscriptionId::fromString($data[1]));
    }
}
