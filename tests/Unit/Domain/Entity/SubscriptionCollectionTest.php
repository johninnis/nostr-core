<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Entity\FilterCollection;
use Innis\Nostr\Core\Domain\Entity\Subscription;
use Innis\Nostr\Core\Domain\Entity\SubscriptionCollection;
use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionCollectionTest extends TestCase
{
    private function createSubscription(?string $id = null): Subscription
    {
        return Subscription::create(
            self::subscriptionId($id ?? 'sub-'.bin2hex(random_bytes(4))),
            new FilterCollection([new Filter()]),
        );
    }

    private static function subscriptionId(string $id): SubscriptionId
    {
        return SubscriptionId::fromString($id) ?? throw new RuntimeException('Expected a valid subscription ID');
    }

    public function testEmptyCollection(): void
    {
        $collection = SubscriptionCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->keys());
        $this->assertSame([], $collection->toArray());
    }

    public function testConstructorValidatesItems(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SubscriptionCollection(['key' => 'not-a-subscription']);
    }

    public function testConstructorValidatesKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SubscriptionCollection([0 => $this->createSubscription()]);
    }

    public function testAddReturnsNewCollection(): void
    {
        $collection = SubscriptionCollection::empty();
        $subscription = $this->createSubscription('sub-1');

        $updated = $collection->add($subscription);

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(1, $updated->count());
        $this->assertTrue($updated->has(self::subscriptionId('sub-1')));
    }

    public function testAddUsesSubscriptionIdAsKey(): void
    {
        $subscription = $this->createSubscription('my-sub');
        $collection = SubscriptionCollection::empty()->add($subscription);

        $this->assertSame(['my-sub'], $collection->keys());
    }

    public function testRemoveReturnsNewCollection(): void
    {
        $subscription = $this->createSubscription('sub-1');
        $collection = SubscriptionCollection::empty()->add($subscription);

        $updated = $collection->remove(self::subscriptionId('sub-1'));

        $this->assertSame(1, $collection->count());
        $this->assertTrue($updated->isEmpty());
    }

    public function testRemoveNonExistentIsNoOp(): void
    {
        $collection = SubscriptionCollection::empty();
        $updated = $collection->remove(self::subscriptionId('nonexistent'));

        $this->assertTrue($updated->isEmpty());
    }

    public function testGetAndHas(): void
    {
        $subscription = $this->createSubscription('sub-1');
        $collection = SubscriptionCollection::empty()->add($subscription);

        $this->assertTrue($collection->has(self::subscriptionId('sub-1')));
        $this->assertFalse($collection->has(self::subscriptionId('unknown')));
        $this->assertSame($subscription, $collection->get(self::subscriptionId('sub-1')));
        $this->assertNull($collection->get(self::subscriptionId('unknown')));
    }

    public function testWithUpdatedState(): void
    {
        $subscription = $this->createSubscription('sub-1');
        $collection = SubscriptionCollection::empty()->add($subscription);

        $updated = $collection->withUpdatedState(self::subscriptionId('sub-1'), SubscriptionState::Active);

        $this->assertSame(SubscriptionState::Pending, $collection->getState(self::subscriptionId('sub-1')));
        $this->assertSame(SubscriptionState::Active, $updated->getState(self::subscriptionId('sub-1')));
    }

    public function testWithUpdatedStateReturnsUnchangedForUnknown(): void
    {
        $collection = SubscriptionCollection::empty();
        $updated = $collection->withUpdatedState(self::subscriptionId('unknown'), SubscriptionState::Active);

        $this->assertTrue($updated->isEmpty());
    }

    public function testFilter(): void
    {
        $sub1 = $this->createSubscription('sub-1');
        $sub2 = $this->createSubscription('sub-2');
        $collection = SubscriptionCollection::empty()
            ->add($sub1)
            ->add($sub2)
            ->withUpdatedState(self::subscriptionId('sub-1'), SubscriptionState::Active);

        $active = $collection->filter(
            static fn (Subscription $s) => SubscriptionState::Active === $s->getState()
        );

        $this->assertSame(1, $active->count());
        $this->assertTrue($active->has(self::subscriptionId('sub-1')));
    }

    public function testIteration(): void
    {
        $sub1 = $this->createSubscription('sub-1');
        $sub2 = $this->createSubscription('sub-2');
        $collection = SubscriptionCollection::empty()->add($sub1)->add($sub2);

        $keys = [];
        foreach ($collection as $key => $value) {
            $keys[] = $key;
            $this->assertInstanceOf(Subscription::class, $value);
        }

        $this->assertSame(['sub-1', 'sub-2'], $keys);
    }

    public function testKeys(): void
    {
        $collection = SubscriptionCollection::empty()
            ->add($this->createSubscription('a'))
            ->add($this->createSubscription('b'));

        $this->assertSame(['a', 'b'], $collection->keys());
    }
}
