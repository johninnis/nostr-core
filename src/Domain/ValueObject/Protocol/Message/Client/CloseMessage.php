<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class CloseMessage extends ClientMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    #[Override]
    public function type(): ClientMessageType
    {
        return ClientMessageType::Close;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, (string) $this->subscriptionId];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data)) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        $parsed = new self($subscriptionId);

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
