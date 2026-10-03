<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use ArrayIterator;
use Countable;
use Innis\Nostr\Core\Domain\Entity\Subscription;
use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use IteratorAggregate;
use Override;

/**
 * @implements IteratorAggregate<int, Subscription>
 */
final readonly class SubscriptionCollection implements IteratorAggregate, Countable
{
    // Deliberate: indexed by the id's string form for lookup only; PHP turns an all-digit id into an integer key, so the index is never exposed and every projection is built from the values — see ADR-0097
    /** @var array<array-key, Subscription> */
    private array $subscriptions;

    /**
     * @param array<array-key, mixed> $subscriptions
     */
    public function __construct(array $subscriptions = [])
    {
        $keyed = [];

        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof Subscription) {
                throw new InvalidArgumentException('All items must be Subscription instances');
            }

            $keyed[(string) $subscription->getId()] = $subscription;
        }

        $this->subscriptions = $keyed;
    }

    /**
     * Returns a collection with $subscription added, replacing in its place any subscription with the same id.
     */
    public function add(Subscription $subscription): self
    {
        return new self([...$this->toArray(), $subscription]);
    }

    public function remove(SubscriptionId $subscriptionId): self
    {
        $subscriptions = $this->subscriptions;
        unset($subscriptions[(string) $subscriptionId]);

        return new self(array_values($subscriptions));
    }

    public function get(SubscriptionId $subscriptionId): ?Subscription
    {
        return $this->subscriptions[(string) $subscriptionId] ?? null;
    }

    public function withUpdatedState(SubscriptionId $subscriptionId, SubscriptionState $state): self
    {
        $key = (string) $subscriptionId;

        if (!isset($this->subscriptions[$key])) {
            return $this;
        }

        $subscriptions = $this->subscriptions;
        $subscriptions[$key] = $subscriptions[$key]->withState($state);

        return new self(array_values($subscriptions));
    }

    /**
     * @param callable(Subscription): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->subscriptions, $predicate)));
    }

    public function isEmpty(): bool
    {
        return [] === $this->subscriptions;
    }

    /**
     * @return list<Subscription>
     */
    public function toArray(): array
    {
        return array_values($this->subscriptions);
    }

    /**
     * @return ArrayIterator<int, Subscription>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    #[Override]
    public function count(): int
    {
        return count($this->subscriptions);
    }
}
