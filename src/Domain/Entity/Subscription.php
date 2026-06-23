<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class Subscription
{
    public function __construct(
        private SubscriptionId $id,
        private FilterCollection $filters,
        private Timestamp $createdAt,
        private SubscriptionState $state = SubscriptionState::Pending,
    ) {
    }

    public static function create(SubscriptionId $id, FilterCollection $filters, SubscriptionState $state = SubscriptionState::Pending, ?Timestamp $createdAt = null): self
    {
        return new self($id, $filters, $createdAt ?? Timestamp::now(), $state);
    }

    public function getId(): SubscriptionId
    {
        return $this->id;
    }

    public function getFilters(): FilterCollection
    {
        return $this->filters;
    }

    public function getCreatedAt(): Timestamp
    {
        return $this->createdAt;
    }

    public function getState(): SubscriptionState
    {
        return $this->state;
    }

    public function isOpen(): bool
    {
        return $this->state->isOpen();
    }

    public function withState(SubscriptionState $state): self
    {
        return new self($this->id, $this->filters, $this->createdAt, $state);
    }

    public function matchesEvent(Event $event): bool
    {
        if (!$this->state->isReceivingEvents()) {
            return false;
        }

        return array_any($this->filters->toArray(), static fn (Filter $filter): bool => $filter->matches($event));
    }

    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'filters' => array_map(static fn (Filter $filter) => $filter->toArray(), $this->filters->toArray()),
            'created_at' => $this->createdAt->toInt(),
            'state' => $this->state->value,
        ];
    }

    public static function fromArray(array $data): ?self
    {
        if (!isset($data['id'], $data['filters'], $data['created_at'])) {
            return null;
        }

        if (!is_array($data['filters']) || !is_int($data['created_at'])) {
            return null;
        }

        $createdAt = Timestamp::tryFromInt($data['created_at']);
        if (null === $createdAt) {
            return null;
        }

        $id = SubscriptionId::fromWire($data['id']);
        if (null === $id) {
            return null;
        }

        $filters = [];
        foreach ($data['filters'] as $filterData) {
            if (!is_array($filterData)) {
                return null;
            }

            $filter = Filter::fromArray($filterData);
            if (null === $filter) {
                return null;
            }

            $filters[] = $filter;
        }

        $state = SubscriptionState::Pending;
        if (isset($data['state'])) {
            if (!is_string($data['state'])) {
                return null;
            }

            $state = SubscriptionState::tryFrom($data['state']);
            if (null === $state) {
                return null;
            }
        }

        return new self(
            $id,
            new FilterCollection($filters),
            $createdAt,
            $state,
        );
    }
}
