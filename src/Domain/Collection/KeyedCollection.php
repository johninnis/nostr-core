<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\IdentityKeyedInterface;
use InvalidArgumentException;

/**
 * @template T of IdentityKeyedInterface
 *
 * @extends TypedCollection<T>
 */
abstract class KeyedCollection extends TypedCollection
{
    // Deliberate: a lazily memoised membership index keyed by each element's own identityKey(), so it is a pure function of the elements and there is no second key to answer from — see ADR-0078
    /** @var array<array-key, true>|null */
    private ?array $membershipIndex = null;

    /**
     * @param T $item
     */
    final public function contains(IdentityKeyedInterface $item): bool
    {
        $this->assertElement($item);

        $this->membershipIndex ??= array_fill_keys($this->identityKeys(), true);

        return isset($this->membershipIndex[$item->identityKey()]);
    }

    final public function unique(): static
    {
        $unique = [];

        foreach ($this->items as $item) {
            $unique[$item->identityKey()] ??= $item;
        }

        return $this->withItems(array_values($unique));
    }

    /**
     * @param self<T> $other
     */
    final public function intersect(self $other): static
    {
        return $this->retainWhereOtherHas($other, true);
    }

    /**
     * @param self<T> $other
     */
    final public function diff(self $other): static
    {
        return $this->retainWhereOtherHas($other, false);
    }

    /**
     * @param self<T> $other
     */
    private function retainWhereOtherHas(self $other, bool $present): static
    {
        // Deliberate: two element types can share an identity key, an event id and a public key both being hex, so a set operation across collection types is refused rather than answered by key — see ADR-0078
        if (!$other instanceof static) {
            throw new InvalidArgumentException(sprintf('Expected a %s, got a %s', static::class, $other::class));
        }

        $otherKeys = array_fill_keys($other->identityKeys(), true);

        return $this->withItems(array_values(array_filter(
            $this->items,
            static fn (IdentityKeyedInterface $item): bool => isset($otherKeys[$item->identityKey()]) === $present,
        )));
    }

    // Deliberate: an element of another type can carry the same identity key as a held one, so it is refused rather than looked up by key — see ADR-0078
    private function assertElement(IdentityKeyedInterface $item): void
    {
        $type = $this->elementType();

        if (!$item instanceof $type) {
            throw new InvalidArgumentException(sprintf('Expected a %s, got a %s', $type, $item::class));
        }
    }

    /**
     * @return list<array-key>
     */
    private function identityKeys(): array
    {
        return $this->mapItems(static fn (IdentityKeyedInterface $item): int|string => $item->identityKey());
    }
}
