<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final class Subscription
{
    public function __construct(
        private SubscriptionId $id,
        private array $filters,
        private Timestamp $createdAt,
        private bool $active = true
    ) {
        foreach ($this->filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new \InvalidArgumentException('All filters must be Filter instances');
            }
        }
    }

    public function getId(): SubscriptionId
    {
        return $this->id;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getCreatedAt(): Timestamp
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function close(): self
    {
        $closed = clone $this;
        $closed->active = false;

        return $closed;
    }

    public function matchesEvent(Event $event): bool
    {
        if (!$this->active) {
            return false;
        }

        foreach ($this->filters as $filter) {
            if ($filter->matches($event)) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'filters' => array_map(fn (Filter $filter) => $filter->toArray(), $this->filters),
            'created_at' => $this->createdAt->toInt(),
            'active' => $this->active
        ];
    }

    public static function fromArray(array $data): self
    {
        $requiredFields = ['id', 'filters', 'created_at'];
        foreach ($requiredFields as $field) {
            if (!\array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $filters = array_map(fn (array $filterData) => Filter::fromArray($filterData), $data['filters']);

        return new self(
            SubscriptionId::fromString($data['id']),
            $filters,
            Timestamp::fromInt($data['created_at']),
            $data['active'] ?? true
        );
    }
}
