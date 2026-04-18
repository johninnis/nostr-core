<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;

final readonly class ReqMessage extends ClientMessage
{
    public const MAX_FILTERS = 20;

    public function __construct(
        private SubscriptionId $subscriptionId,
        private array $filters,
    ) {
        if (empty($this->filters)) {
            throw new InvalidArgumentException('REQ message must have at least one filter');
        }

        if (count($this->filters) > self::MAX_FILTERS) {
            throw new InvalidArgumentException(sprintf('REQ message may contain at most %d filters', self::MAX_FILTERS));
        }

        foreach ($this->filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new InvalidArgumentException('All filters must be Filter instances');
            }
        }
    }

    public function getType(): string
    {
        return 'REQ';
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
        $message = ['REQ', (string) $this->subscriptionId];

        foreach ($this->filters as $filter) {
            $message[] = $filter->toArray();
        }

        return $message;
    }

    public static function fromArray(array $data): static
    {
        if (count($data) < 3 || 'REQ' !== $data[0]) {
            throw new InvalidArgumentException('Invalid REQ message format');
        }

        if (count($data) - 2 > self::MAX_FILTERS) {
            throw new InvalidArgumentException(sprintf('REQ message may contain at most %d filters', self::MAX_FILTERS));
        }

        $subscriptionId = SubscriptionId::fromString($data[1]);
        $filters = [];

        for ($i = 2; $i < count($data); ++$i) {
            $filters[] = Filter::fromArray($data[$i]);
        }

        return new self($subscriptionId, $filters);
    }
}
