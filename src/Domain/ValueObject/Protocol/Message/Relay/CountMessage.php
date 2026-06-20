<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

final readonly class CountMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private int $count,
    ) {
        if ($this->count < 0) {
            throw new InvalidArgumentException('Count cannot be negative');
        }
    }

    #[Override]
    public function getType(): string
    {
        return 'COUNT';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    #[Override]
    public function toArray(): array
    {
        return ['COUNT', (string) $this->subscriptionId, ['count' => $this->count]];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (3 !== count($data) || 'COUNT' !== $data[0] || !is_string($data[1])) {
            return null;
        }

        if (!is_array($data[2]) || !array_key_exists('count', $data[2])) {
            return null;
        }

        $count = (int) $data[2]['count'];

        if ($count < 0) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromString($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        return new self(
            $subscriptionId,
            $count,
        );
    }
}
