<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;

final readonly class CloseMessage extends ClientMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    public function getType(): string
    {
        return 'CLOSE';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function toArray(): array
    {
        return ['CLOSE', (string) $this->subscriptionId];
    }

    public static function fromArray(array $data): static
    {
        if (\count($data) !== 2 || $data[0] !== 'CLOSE') {
            throw new \InvalidArgumentException('Invalid CLOSE message format');
        }

        return new self(SubscriptionId::fromString($data[1]));
    }
}
