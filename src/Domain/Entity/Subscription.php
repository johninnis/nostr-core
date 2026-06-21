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
        private FilterCollection $filters,
        private Timestamp $createdAt,
        private SubscriptionState $state = SubscriptionState::PENDING,
    ) {
    }

    public static function create(SubscriptionId $id, FilterCollection $filters, SubscriptionState $state = SubscriptionState::PENDING, ?Timestamp $createdAt = null): self
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

    public static function fromArray(array $data): self
    {
        $requiredFields = ['id', 'filters', 'created_at'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $filters = new FilterCollection(
            array_map(static fn (array $filterData) => Filter::fromArray($filterData), $data['filters'])
        );

        $state = isset($data['state'])
            ? SubscriptionState::from($data['state'])
            : SubscriptionState::PENDING;

        $id = is_string($data['id']) ? SubscriptionId::fromString($data['id']) : null;

        if (null === $id) {
            throw new InvalidArgumentException('Invalid subscription ID');
        }

        return new self(
            $id,
            $filters,
            Timestamp::fromInt($data['created_at']),
            $state,
        );
    }
}
