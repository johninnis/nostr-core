<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\EventCount;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class CountMessage extends RelayMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private EventCount $count,
    ) {
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Count;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    // Deliberate: one value, never a count beside a nullable flag — see ADR-0064
    public function getCount(): EventCount
    {
        return $this->count;
    }

    #[Override]
    protected function toPayload(): array
    {
        $count = ['count' => $this->count->toInt()];

        if ($this->count->isApproximate()) {
            $count['approximate'] = true;
        }

        return [(string) $this->subscriptionId, $count];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        $fields = JsonWireFormat::objectFields($payload[1] ?? null);

        if (null === $fields) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($payload[0]);
        $count = JsonWireFormat::intField($fields, 'count');
        $approximate = $fields['approximate'] ?? false;

        if (null === $subscriptionId || null === $count || !is_bool($approximate)) {
            return null;
        }

        $eventCount = EventCount::tryFrom($count, $approximate);

        return null === $eventCount ? null : new self($subscriptionId, $eventCount);
    }
}
