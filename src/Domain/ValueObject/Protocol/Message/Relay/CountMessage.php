<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

final readonly class CountMessage extends RelayMessage
{
    protected const string TYPE = 'COUNT';

    public function __construct(
        private SubscriptionId $subscriptionId,
        private int $count,
    ) {
        if ($this->count < 0) {
            throw new InvalidArgumentException('Count cannot be negative');
        }
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
        return [self::TYPE, (string) $this->subscriptionId, ['count' => $this->count]];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (3 !== count($data) || self::TYPE !== $data[0]) {
            return null;
        }

        if (!is_array($data[2]) || !array_key_exists('count', $data[2])) {
            return null;
        }

        $count = $data[2]['count'];

        if (!is_int($count) || $count < 0) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);

        if (null === $subscriptionId) {
            return null;
        }

        return new self(
            $subscriptionId,
            $count,
        );
    }
}
