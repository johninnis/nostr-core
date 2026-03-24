<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use ArrayIterator;
use Countable;
use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use IteratorAggregate;

final class SubscriptionCollection implements IteratorAggregate, Countable
{
    private array $subscriptions;

    public function __construct(array $subscriptions = [])
    {
        foreach ($subscriptions as $key => $subscription) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('All keys must be subscription ID strings');
            }
            if (!$subscription instanceof Subscription) {
                throw new InvalidArgumentException('All items must be Subscription instances');
            }
        }
        $this->subscriptions = $subscriptions;
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(Subscription $subscription): self
    {
        $subscriptions = $this->subscriptions;
        $subscriptions[(string) $subscription->getId()] = $subscription;

        return new self($subscriptions);
    }

    public function remove(SubscriptionId $subscriptionId): self
    {
        $subscriptions = $this->subscriptions;
        unset($subscriptions[(string) $subscriptionId]);

        return new self($subscriptions);
    }

    public function get(SubscriptionId $subscriptionId): ?Subscription
    {
        return $this->subscriptions[(string) $subscriptionId] ?? null;
    }

    public function has(SubscriptionId $subscriptionId): bool
    {
        return isset($this->subscriptions[(string) $subscriptionId]);
    }

    public function withUpdatedState(SubscriptionId $subscriptionId, SubscriptionState $state): self
    {
        $key = (string) $subscriptionId;

        if (!isset($this->subscriptions[$key])) {
            return $this;
        }

        $subscriptions = $this->subscriptions;
        $subscriptions[$key] = $subscriptions[$key]->withState($state);

        return new self($subscriptions);
    }

    public function getState(SubscriptionId $subscriptionId): ?SubscriptionState
    {
        return $this->get($subscriptionId)?->getState();
    }

    public function keys(): array
    {
        return array_keys($this->subscriptions);
    }

    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->subscriptions, $predicate));
    }

    public function isEmpty(): bool
    {
        return empty($this->subscriptions);
    }

    public function toArray(): array
    {
        return $this->subscriptions;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->subscriptions);
    }

    public function count(): int
    {
        return count($this->subscriptions);
    }
}
