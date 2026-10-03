<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Subscription;
use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionTest extends TestCase
{
    public function testCreateSetsDefaultPendingState(): void
    {
        $id = SubscriptionId::generate();
        $filters = new FilterCollection([Filter::from()]);
        $subscription = Subscription::create($id, $filters);

        $this->assertTrue($id->equals($subscription->getId()));
        $this->assertSame($filters, $subscription->getFilters());
        $this->assertSame(SubscriptionState::Pending, $subscription->getState());
    }

    public function testCreateKeepsTheCreationInstantItIsGiven(): void
    {
        $subscription = Subscription::create(SubscriptionId::generate(), new FilterCollection([Filter::from()]), createdAt: Timestamp::fromInt(1_700_000_000));

        $this->assertSame(1_700_000_000, $subscription->getCreatedAt()->toInt());
    }

    public function testCreateAcceptsExplicitState(): void
    {
        $subscription = Subscription::create(SubscriptionId::generate(), new FilterCollection([Filter::from()]), SubscriptionState::Active);

        $this->assertSame(SubscriptionState::Active, $subscription->getState());
    }

    public function testConstructorValidatesFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FilterCollection(['not-a-filter']);
    }

    public function testWithStateReturnsNewInstance(): void
    {
        $subscription = Subscription::create(SubscriptionId::generate(), new FilterCollection([Filter::from()]));
        $updated = $subscription->withState(SubscriptionState::Active);

        $this->assertSame(SubscriptionState::Pending, $subscription->getState());
        $this->assertSame(SubscriptionState::Active, $updated->getState());
        $this->assertTrue($subscription->getId()->equals($updated->getId()));
        $this->assertTrue($subscription->getCreatedAt()->equals($updated->getCreatedAt()));
    }

    public function testIsOpenDelegatesToState(): void
    {
        $subscription = Subscription::create(SubscriptionId::generate(), new FilterCollection([Filter::from()]));

        $this->assertTrue($subscription->isOpen());
        $this->assertTrue($subscription->withState(SubscriptionState::Active)->isOpen());
        $this->assertTrue($subscription->withState(SubscriptionState::Live)->isOpen());
        $this->assertFalse($subscription->withState(SubscriptionState::ClosedByRelay)->isOpen());
        $this->assertFalse($subscription->withState(SubscriptionState::ClosedByClient)->isOpen());
    }

    public function testMatchesEventWhenReceivingEvents(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]));
        $subscription = Subscription::create(SubscriptionId::generate(), new FilterCollection([$filter]));

        $this->assertFalse($subscription->matchesEvent($event));

        $active = $subscription->withState(SubscriptionState::Active);
        $this->assertTrue($active->matchesEvent($event));

        $live = $subscription->withState(SubscriptionState::Live);
        $this->assertTrue($live->matchesEvent($event));
    }

    public function testMatchesEventReturnsFalseWhenClosed(): void
    {
        $keyPair = KeyMother::alice();
        $event = EventMother::fromRumour(Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $filter = Filter::from(kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]));
        $closed = Subscription::create(SubscriptionId::generate(), new FilterCollection([$filter]))
            ->withState(SubscriptionState::ClosedByClient);

        $this->assertFalse($closed->matchesEvent($event));
    }

    public function testToArray(): void
    {
        $id = SubscriptionId::tryFromString('test-sub') ?? throw new RuntimeException('Expected a valid subscription ID');
        $filter = Filter::from(kinds: EventKindCollection::fromInts([EventKind::TEXT_NOTE]));
        $subscription = Subscription::create($id, new FilterCollection([$filter]), SubscriptionState::Live, Timestamp::fromInt(1700000000));

        $array = $subscription->toArray();

        $this->assertSame('test-sub', $array['id']);
        $this->assertSame(1700000000, $array['created_at']);
        $this->assertSame('live', $array['state']);
        $this->assertIsArray($array['filters']);
        $this->assertCount(1, $array['filters']);
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArray(): void
    {
        $this->assertNull(Subscription::tryFromArray('sub'));
    }

    public function testTryFromArrayWithState(): void
    {
        $data = [
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 1700000000,
            'state' => 'active',
        ];

        $subscription = Subscription::tryFromArray($data);

        $this->assertNotNull($subscription);
        $this->assertSame('test-sub', (string) $subscription->getId());
        $this->assertSame(SubscriptionState::Active, $subscription->getState());
    }

    public function testTryFromArrayDefaultsToPendingWhenStateAbsent(): void
    {
        $data = [
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 1700000000,
        ];

        $subscription = Subscription::tryFromArray($data);

        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionState::Pending, $subscription->getState());
    }

    public function testTryFromArrayReturnsNullWhenRequiredFieldMissing(): void
    {
        $this->assertNull(Subscription::tryFromArray(['id' => 'test']));
    }

    public function testTryFromArrayReturnsNullWhenStateInvalid(): void
    {
        $this->assertNull(Subscription::tryFromArray([
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 1700000000,
            'state' => 'bogus',
        ]));
    }

    public function testTryFromArrayReturnsNullWhenStateIsNull(): void
    {
        $this->assertNull(Subscription::tryFromArray([
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 1700000000,
            'state' => null,
        ]));
    }

    public function testTryFromArrayReturnsNullWhenFilterMalformed(): void
    {
        $this->assertNull(Subscription::tryFromArray([
            'id' => 'test-sub',
            'filters' => ['not-an-array'],
            'created_at' => 1700000000,
        ]));
    }

    public function testTryFromArrayReturnsNullWhenCreatedAtNotInteger(): void
    {
        $this->assertNull(Subscription::tryFromArray([
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => 'soon',
        ]));
    }

    public function testTryFromArrayReturnsNullWhenCreatedAtNegative(): void
    {
        $this->assertNull(Subscription::tryFromArray([
            'id' => 'test-sub',
            'filters' => [['kinds' => [1]]],
            'created_at' => -1,
        ]));
    }
}
