<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;

final readonly class Subscription
{
    public function __construct(
        private SubscriptionId $id,
        private array $filters,
        private Timestamp $createdAt,
        private SubscriptionState $state = SubscriptionState::PENDING,
    ) {
        foreach ($this->filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new InvalidArgumentException('All filters must be Filter instances');
            }
        }
    }

    public static function create(SubscriptionId $id, array $filters): self
    {
        return new self($id, $filters, Timestamp::now());
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
            'filters' => array_map(static fn (Filter $filter) => $filter->toArray(), $this->filters),
            'created_at' => $this->createdAt->toInt(),
            'state' => $this->state->value,
        ];
    }

    public static function fromArray(array $data): self
    {
        $requiredFields = ['id', 'filters', 'created_at'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $filters = array_map(static fn (array $filterData) => Filter::fromArray($filterData), $data['filters']);

        $state = isset($data['state'])
            ? SubscriptionState::from($data['state'])
            : (($data['active'] ?? true) ? SubscriptionState::ACTIVE : SubscriptionState::CLOSED_BY_CLIENT);

        return new self(
            SubscriptionId::fromString($data['id']),
            $filters,
            Timestamp::fromInt($data['created_at']),
            $state,
        );
    }
}
