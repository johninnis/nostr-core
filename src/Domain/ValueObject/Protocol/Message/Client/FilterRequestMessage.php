<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

abstract readonly class FilterRequestMessage extends ClientMessage
{
    public const int MAX_FILTERS = 20;

    final public function __construct(
        private SubscriptionId $subscriptionId,
        private FilterCollection $filters,
    ) {
        if ($this->filters->isEmpty()) {
            throw new InvalidArgumentException(sprintf('%s message must have at least one filter', $this->type()->value));
        }

        if (count($this->filters) > self::MAX_FILTERS) {
            throw new InvalidArgumentException(sprintf('%s message may contain at most %d filters', $this->type()->value, self::MAX_FILTERS));
        }
    }

    final public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    final public function getFilters(): FilterCollection
    {
        return $this->filters;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    final public function toArray(): array
    {
        return [
            $this->type()->value,
            (string) $this->subscriptionId,
            ...array_map(static fn (Filter $filter) => $filter->jsonSerialize(), $this->filters->toArray()),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    final public static function fromArray(array $data): ?static
    {
        if (count($data) < 3) {
            return null;
        }

        if (count($data) - 2 > self::MAX_FILTERS) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromWire($data[1]);
        if (null === $subscriptionId) {
            return null;
        }

        $filters = FilterCollection::fromWire(array_slice($data, 2));
        if (null === $filters) {
            return null;
        }

        $parsed = new static($subscriptionId, $filters);

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
