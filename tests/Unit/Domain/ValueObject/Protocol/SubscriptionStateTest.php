<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use PHPUnit\Framework\TestCase;

final class SubscriptionStateTest extends TestCase
{
    public function testPendingStatePredicates(): void
    {
        $state = SubscriptionState::PENDING;

        $this->assertTrue($state->isPending());
        $this->assertTrue($state->isOpen());
        $this->assertFalse($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertFalse($state->isTerminal());
    }

    public function testActiveStatePredicates(): void
    {
        $state = SubscriptionState::ACTIVE;

        $this->assertFalse($state->isPending());
        $this->assertTrue($state->isOpen());
        $this->assertTrue($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertFalse($state->isTerminal());
    }

    public function testLiveStatePredicates(): void
    {
        $state = SubscriptionState::LIVE;

        $this->assertFalse($state->isPending());
        $this->assertTrue($state->isOpen());
        $this->assertTrue($state->isReceivingEvents());
        $this->assertTrue($state->isLive());
        $this->assertFalse($state->isTerminal());
    }

    public function testClosedByRelayStatePredicates(): void
    {
        $state = SubscriptionState::CLOSED_BY_RELAY;

        $this->assertFalse($state->isPending());
        $this->assertFalse($state->isOpen());
        $this->assertFalse($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertTrue($state->isTerminal());
    }

    public function testClosedByClientStatePredicates(): void
    {
        $state = SubscriptionState::CLOSED_BY_CLIENT;

        $this->assertFalse($state->isPending());
        $this->assertFalse($state->isOpen());
        $this->assertFalse($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertTrue($state->isTerminal());
    }

    public function testBackedStringValues(): void
    {
        $this->assertSame('pending', SubscriptionState::PENDING->value);
        $this->assertSame('active', SubscriptionState::ACTIVE->value);
        $this->assertSame('live', SubscriptionState::LIVE->value);
        $this->assertSame('closed_by_relay', SubscriptionState::CLOSED_BY_RELAY->value);
        $this->assertSame('closed_by_client', SubscriptionState::CLOSED_BY_CLIENT->value);
    }

    public function testFromStringValue(): void
    {
        $this->assertSame(SubscriptionState::PENDING, SubscriptionState::from('pending'));
        $this->assertSame(SubscriptionState::ACTIVE, SubscriptionState::from('active'));
        $this->assertSame(SubscriptionState::LIVE, SubscriptionState::from('live'));
        $this->assertSame(SubscriptionState::CLOSED_BY_RELAY, SubscriptionState::from('closed_by_relay'));
        $this->assertSame(SubscriptionState::CLOSED_BY_CLIENT, SubscriptionState::from('closed_by_client'));
    }

    public function testInvalidStringValueReturnsNull(): void
    {
        $this->assertNull(SubscriptionState::tryFrom('invalid'));
    }
}
