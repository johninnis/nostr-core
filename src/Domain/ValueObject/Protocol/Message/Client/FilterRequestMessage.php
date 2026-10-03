<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

abstract readonly class FilterRequestMessage extends ClientMessage
{
    final private function __construct(
        private SubscriptionId $subscriptionId,
        private FilterCollection $filters,
    ) {
    }

    final public static function tryFrom(SubscriptionId $subscriptionId, FilterCollection $filters): ?static
    {
        if ($filters->isEmpty()) {
            return null;
        }

        return new static($subscriptionId, $filters);
    }

    final public static function from(SubscriptionId $subscriptionId, FilterCollection $filters): static
    {
        return static::tryFrom($subscriptionId, $filters)
            ?? throw new InvalidArgumentException(static::type()->value.' message must carry at least one filter');
    }

    final public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    final public function getFilters(): FilterCollection
    {
        return $this->filters;
    }

    #[Override]
    final protected function toPayload(): array
    {
        return [
            (string) $this->subscriptionId,
            ...$this->filters->toJsonArray(),
        ];
    }

    #[Override]
    final protected static function tryFromPayload(array $payload): ?static
    {
        $subscriptionId = SubscriptionId::tryFromString($payload[0] ?? null);
        $filterValues = array_slice($payload, 1);

        // Deliberate: an empty list is a JSON array, never the filter that matches everything; that filter arrives as a stdClass — see ADR-0112
        if (array_any($filterValues, static fn (mixed $filter): bool => [] === $filter)) {
            return null;
        }

        $filters = FilterCollection::tryFromArray($filterValues);

        return null === $subscriptionId || null === $filters ? null : static::tryFrom($subscriptionId, $filters);
    }
}
