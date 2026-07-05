<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

final readonly class CountMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private int $count,
        private ?bool $approximate = null,
    ) {
        if ($this->count < 0) {
            throw new InvalidArgumentException('Count cannot be negative');
        }
    }

    #[Override]
    public function type(): RelayMessageType
    {
        return RelayMessageType::Count;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getApproximate(): ?bool
    {
        return $this->approximate;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        $payload = ['count' => $this->count];

        if (null !== $this->approximate) {
            $payload['approximate'] = $this->approximate;
        }

        return [$this->type()->value, (string) $this->subscriptionId, $payload];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function tryFromArray(array $data): ?static
    {
        if (!array_is_list($data) || 3 !== count($data)) {
            return null;
        }

        if (!is_array($data[2]) || !array_key_exists('count', $data[2])) {
            return null;
        }

        $count = $data[2]['count'];

        if (!is_int($count) || $count < 0) {
            return null;
        }

        $approximate = $data[2]['approximate'] ?? null;

        if (null !== $approximate && !is_bool($approximate)) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        $parsed = new self(
            $subscriptionId,
            $count,
            $approximate,
        );

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
