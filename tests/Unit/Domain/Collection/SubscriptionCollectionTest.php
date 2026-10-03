<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Collection\SubscriptionCollection;
use Innis\Nostr\Core\Domain\Entity\Subscription;
use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionCollectionTest extends TestCase
{
    private function createSubscription(string $id): Subscription
    {
        return Subscription::create(
            self::subscriptionId($id),
            new FilterCollection([Filter::from()]),
        );
    }

    private static function subscriptionId(string $id): SubscriptionId
    {
        return SubscriptionId::tryFromString($id) ?? throw new RuntimeException('Expected a valid subscription ID');
    }

    public function testEmptyCollection(): void
    {
        $collection = new SubscriptionCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->toArray());
    }

    public function testConstructorValidatesItems(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SubscriptionCollection(['not-a-subscription']);
    }

    public function testConstructorKeysBySubscriptionId(): void
    {
        $collection = new SubscriptionCollection([
            $this->createSubscription('alpha'),
            $this->createSubscription('beta'),
        ]);

        $this->assertSame(['alpha', 'beta'], self::idStrings($collection));
        $this->assertNotNull($collection->get(self::subscriptionId('alpha')));
        $this->assertNotNull($collection->get(self::subscriptionId('beta')));
    }

    public function testAddReturnsNewCollection(): void
    {
        $collection = new SubscriptionCollection();
        $subscription = $this->createSubscription('sub-1');

        $updated = $collection->add($subscription);

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(1, $updated->count());
        $this->assertNotNull($updated->get(self::subscriptionId('sub-1')));
    }

    public function testAddUsesSubscriptionIdAsKey(): void
    {
        $subscription = $this->createSubscription('my-sub');
        $collection = new SubscriptionCollection()->add($subscription);

        $this->assertSame(['my-sub'], self::idStrings($collection));
    }

    public function testRemoveReturnsNewCollection(): void
    {
        $subscription = $this->createSubscription('sub-1');
        $collection = new SubscriptionCollection()->add($subscription);

        $updated = $collection->remove(self::subscriptionId('sub-1'));

        $this->assertSame(1, $collection->count());
        $this->assertTrue($updated->isEmpty());
    }

    public function testRemoveNonExistentIsNoOp(): void
    {
        $collection = new SubscriptionCollection();
        $updated = $collection->remove(self::subscriptionId('nonexistent'));

        $this->assertTrue($updated->isEmpty());
    }

    public function testGetAndHas(): void
    {
        $subscription = $this->createSubscription('sub-1');
        $collection = new SubscriptionCollection()->add($subscription);

        $this->assertNotNull($collection->get(self::subscriptionId('sub-1')));
        $this->assertNull($collection->get(self::subscriptionId('unknown')));
        $this->assertSame($subscription, $collection->get(self::subscriptionId('sub-1')));
        $this->assertNull($collection->get(self::subscriptionId('unknown')));
    }

    public function testWithUpdatedState(): void
    {
        $subscription = $this->createSubscription('sub-1');
        $collection = new SubscriptionCollection()->add($subscription);

        $updated = $collection->withUpdatedState(self::subscriptionId('sub-1'), SubscriptionState::Active);

        $this->assertSame(SubscriptionState::Pending, $collection->get(self::subscriptionId('sub-1'))?->getState());
        $this->assertSame(SubscriptionState::Active, $updated->get(self::subscriptionId('sub-1'))?->getState());
    }

    public function testWithUpdatedStateReturnsUnchangedForUnknown(): void
    {
        $collection = new SubscriptionCollection();
        $updated = $collection->withUpdatedState(self::subscriptionId('unknown'), SubscriptionState::Active);

        $this->assertTrue($updated->isEmpty());
    }

    public function testFilter(): void
    {
        $sub1 = $this->createSubscription('sub-1');
        $sub2 = $this->createSubscription('sub-2');
        $collection = new SubscriptionCollection()
            ->add($sub1)
            ->add($sub2)
            ->withUpdatedState(self::subscriptionId('sub-1'), SubscriptionState::Active);

        $active = $collection->filter(
            static fn (Subscription $s) => SubscriptionState::Active === $s->getState()
        );

        $this->assertSame(1, $active->count());
        $this->assertNotNull($active->get(self::subscriptionId('sub-1')));
    }

    public function testIteration(): void
    {
        $sub1 = $this->createSubscription('sub-1');
        $sub2 = $this->createSubscription('sub-2');
        $collection = new SubscriptionCollection()->add($sub1)->add($sub2);

        $this->assertSame([0 => $sub1, 1 => $sub2], iterator_to_array($collection));
    }

    public function testKeys(): void
    {
        $collection = new SubscriptionCollection()
            ->add($this->createSubscription('a'))
            ->add($this->createSubscription('b'));

        $this->assertSame(['a', 'b'], self::idStrings($collection));
    }

    public function testAllDigitIdsStayStringTypedIds(): void
    {
        $subscription = $this->createSubscription('12345678');
        $collection = new SubscriptionCollection([$subscription]);

        $this->assertSame([$subscription], $collection->toArray());
        $this->assertSame([0 => $subscription], iterator_to_array($collection));
        $this->assertSame($subscription, $collection->get(self::subscriptionId('12345678')));
    }

    public function testAddingAnExistingIdReplacesItInPlace(): void
    {
        $first = $this->createSubscription('sub-1');
        $replacement = $this->createSubscription('sub-1');
        $collection = new SubscriptionCollection([$first, $this->createSubscription('sub-2')])->add($replacement);

        $this->assertSame(['sub-1', 'sub-2'], self::idStrings($collection));
        $this->assertSame($replacement, $collection->get(self::subscriptionId('sub-1')));
    }

    /**
     * @return list<string>
     */
    private static function idStrings(SubscriptionCollection $collection): array
    {
        return array_map(static fn (Subscription $subscription): string => (string) $subscription->getId(), $collection->toArray());
    }
}
