<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Entity\Subscription;
use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testCreateSetsDefaultPendingState(): void
    {
        $id = SubscriptionId::generate();
        $filters = [new Filter()];
        $subscription = Subscription::create($id, $filters);

        $this->assertTrue($id->equals($subscription->getId()));
        $this->assertSame($filters, $subscription->getFilters());
        $this->assertSame(SubscriptionState::PENDING, $subscription->getState());
        $this->assertSame(time(), $subscription->getCreatedAt()->toInt());
    }

    public function testConstructorValidatesFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Subscription(
            SubscriptionId::generate(),
            ['not-a-filter'],
            Timestamp::now(),
        );
    }

    public function testWithStateReturnsNewInstance(): void
    {
        $subscription = Subscription::create(SubscriptionId::generate(), [new Filter()]);
        $updated = $subscription->withState(SubscriptionState::ACTIVE);

        $this->assertSame(SubscriptionState::PENDING, $subscription->getState());
        $this->assertSame(SubscriptionState::ACTIVE, $updated->getState());
        $this->assertTrue($subscription->getId()->equals($updated->getId()));
        $this->assertTrue($subscription->getCreatedAt()->equals($updated->getCreatedAt()));
    }

    public function testIsOpenDelegatesToState(): void
    {
        $subscription = Subscription::create(SubscriptionId::generate(), [new Filter()]);

        $this->assertTrue($subscription->isOpen());
        $this->assertTrue($subscription->withState(SubscriptionState::ACTIVE)->isOpen());
        $this->assertTrue($subscription->withState(SubscriptionState::LIVE)->isOpen());
        $this->assertFalse($subscription->withState(SubscriptionState::CLOSED_BY_RELAY)->isOpen());
        $this->assertFalse($subscription->withState(SubscriptionState::CLOSED_BY_CLIENT)->isOpen());
    }

    public function testMatchesEventWhenReceivingEvents(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test'),
        );

        $filter = new Filter(kinds: [EventKind::TEXT_NOTE]);
        $subscription = Subscription::create(SubscriptionId::generate(), [$filter]);

        $this->assertFalse($subscription->matchesEvent($event));

        $active = $subscription->withState(SubscriptionState::ACTIVE);
        $this->assertTrue($active->matchesEvent($event));

        $live = $subscription->withState(SubscriptionState::LIVE);
        $this->assertTrue($live->matchesEvent($event));
    }

    public function testMatchesEventReturnsFalseWhenClosed(): void
    {
        $keyPair = KeyPair::generate();
        $event = new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test'),
        );

        $filter = new Filter(kinds: [EventKind::TEXT_NOTE]);
        $closed = Subscription::create(SubscriptionId::generate(), [$filter])
            ->withState(SubscriptionState::CLOSED_BY_CLIENT);

        $this->assertFalse($closed->matchesEvent($event));
    }

    public function testToArray(): void
    {
        $id = SubscriptionId::fromString('test-sub');
        $filter = new Filter(kinds: [EventKind::TEXT_NOTE]);
        $subscription = new Subscription($id, [$filter], Timestamp::fromInt(1700000000), SubscriptionState::LIVE);

        $array = $subscription->toArray();

        $this->assertSame('test-sub', $array['id']);
        $this->assertSame(1700000000, $array['created_at']);
        $this->assertSame('live', $array['state']);
        $this->assertCount(1, $array['filters']);
    }

    public function testFromArrayWithState(): void
    {
        $data = [
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 1700000000,
            'state' => 'active',
        ];

        $subscription = Subscription::fromArray($data);

        $this->assertSame('test-sub', (string) $subscription->getId());
        $this->assertSame(SubscriptionState::ACTIVE, $subscription->getState());
    }

    public function testFromArrayWithLegacyActiveBool(): void
    {
        $data = [
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 1700000000,
            'active' => false,
        ];

        $subscription = Subscription::fromArray($data);

        $this->assertSame(SubscriptionState::CLOSED_BY_CLIENT, $subscription->getState());
    }

    public function testFromArrayMissingRequiredField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Subscription::fromArray(['id' => 'test']);
    }
}
