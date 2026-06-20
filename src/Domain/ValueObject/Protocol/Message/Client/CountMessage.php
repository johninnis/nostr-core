<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Entity\FilterCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use Override;

final readonly class CountMessage extends ClientMessage
{
    public const int MAX_FILTERS = 20;

    public function __construct(
        private SubscriptionId $subscriptionId,
        private FilterCollection $filters,
    ) {
        if ($this->filters->isEmpty()) {
            throw new InvalidArgumentException('COUNT message must have at least one filter');
        }

        if (count($this->filters) > self::MAX_FILTERS) {
            throw new InvalidArgumentException(sprintf('COUNT message may contain at most %d filters', self::MAX_FILTERS));
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

    public function getFilters(): FilterCollection
    {
        return $this->filters;
    }

    #[Override]
    public function toArray(): array
    {
        $message = ['COUNT', (string) $this->subscriptionId];

        foreach ($this->filters as $filter) {
            $message[] = $filter->jsonSerialize();
        }

        return $message;
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (count($data) < 3 || 'COUNT' !== $data[0] || !is_string($data[1])) {
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

        for ($i = 2; $i < count($data); ++$i) {
            $filter = Filter::fromArray($data[$i]);
            if (null === $filter) {
                return null;
            }
            $filters[] = $filter;
        }

        return new self($subscriptionId, new FilterCollection($filters));
    }
}
