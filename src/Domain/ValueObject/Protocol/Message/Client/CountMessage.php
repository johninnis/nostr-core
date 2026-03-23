<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;

final readonly class CountMessage extends ClientMessage
{
    public function __construct(
        private SubscriptionId $subscriptionId,
        private array $filters
    ) {
        if (empty($this->filters)) {
            throw new \InvalidArgumentException('COUNT message must have at least one filter');
        }

        foreach ($this->filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new \InvalidArgumentException('All filters must be Filter instances');
            }
        }
    }

    public function getType(): string
    {
        return 'COUNT';
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function toArray(): array
    {
        $message = ['COUNT', (string) $this->subscriptionId];

        foreach ($this->filters as $filter) {
            $message[] = $filter->toArray();
        }

        return $message;
    }

    public static function fromArray(array $data): static
    {
        if (\count($data) < 3 || $data[0] !== 'COUNT') {
            throw new \InvalidArgumentException('Invalid COUNT message format');
        }

        $subscriptionId = SubscriptionId::fromString($data[1]);
        $filters = [];

        for ($i = 2; $i < \count($data); $i++) {
            $filters[] = Filter::fromArray($data[$i]);
        }

        return new self($subscriptionId, $filters);
    }
}
