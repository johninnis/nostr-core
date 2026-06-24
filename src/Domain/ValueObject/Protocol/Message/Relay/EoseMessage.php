<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class EoseMessage extends RelayMessage
{
    protected const string TYPE = 'EOSE';

    public function __construct(private SubscriptionId $subscriptionId)
    {
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
        return [self::TYPE, (string) $this->subscriptionId];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || self::TYPE !== $data[0]) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        return new self($subscriptionId);
    }
}
