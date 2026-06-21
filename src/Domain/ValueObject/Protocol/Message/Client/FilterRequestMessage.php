<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Entity\FilterCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

abstract readonly class FilterRequestMessage extends ClientMessage
{
    public const int MAX_FILTERS = 20;

    protected const string TYPE = '';

    final public function __construct(
        private SubscriptionId $subscriptionId,
        private FilterCollection $filters,
    ) {
        if ($this->filters->isEmpty()) {
            throw new InvalidArgumentException(sprintf('%s message must have at least one filter', static::TYPE));
        }

        if (count($this->filters) > self::MAX_FILTERS) {
            throw new InvalidArgumentException(sprintf('%s message may contain at most %d filters', static::TYPE, self::MAX_FILTERS));
        }
    }

    #[Override]
    final public function getType(): string
    {
        return static::TYPE;
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
    final public function toArray(): array
    {
        return [
            static::TYPE,
            (string) $this->subscriptionId,
            ...array_map(static fn (Filter $filter) => $filter->jsonSerialize(), $this->filters->toArray()),
        ];
    }

    #[Override]
    final public static function fromArray(array $data): ?static
    {
        if (count($data) < 3 || static::TYPE !== $data[0] || !is_string($data[1])) {
            return null;
        }

        if (count($data) - 2 > self::MAX_FILTERS) {
            return null;
        }

        $subscriptionId = SubscriptionId::fromString($data[1]);
        if (null === $subscriptionId) {
            return null;
        }

        $filters = [];
        foreach (array_slice($data, 2) as $filterData) {
            if (!is_array($filterData)) {
                return null;
            }

            $filter = Filter::fromArray($filterData);
            if (null === $filter) {
                return null;
            }

            $filters[] = $filter;
        }

        return new static($subscriptionId, new FilterCollection($filters));
    }
}
