<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use PHPUnit\Framework\TestCase;

final class SubscriptionStateTest extends TestCase
{
    public function testPendingStatePredicates(): void
    {
        $state = SubscriptionState::Pending;

        $this->assertTrue($state->isPending());
        $this->assertTrue($state->isOpen());
        $this->assertFalse($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertFalse($state->isTerminal());
    }

    public function testActiveStatePredicates(): void
    {
        $state = SubscriptionState::Active;

        $this->assertFalse($state->isPending());
        $this->assertTrue($state->isOpen());
        $this->assertTrue($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertFalse($state->isTerminal());
    }

    public function testLiveStatePredicates(): void
    {
        $state = SubscriptionState::Live;

        $this->assertFalse($state->isPending());
        $this->assertTrue($state->isOpen());
        $this->assertTrue($state->isReceivingEvents());
        $this->assertTrue($state->isLive());
        $this->assertFalse($state->isTerminal());
    }

    public function testClosedByRelayStatePredicates(): void
    {
        $state = SubscriptionState::ClosedByRelay;

        $this->assertFalse($state->isPending());
        $this->assertFalse($state->isOpen());
        $this->assertFalse($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertTrue($state->isTerminal());
    }

    public function testClosedByClientStatePredicates(): void
    {
        $state = SubscriptionState::ClosedByClient;

        $this->assertFalse($state->isPending());
        $this->assertFalse($state->isOpen());
        $this->assertFalse($state->isReceivingEvents());
        $this->assertFalse($state->isLive());
        $this->assertTrue($state->isTerminal());
    }

    public function testBackedStringValues(): void
    {
        $this->assertSame('pending', SubscriptionState::Pending->value);
        $this->assertSame('active', SubscriptionState::Active->value);
        $this->assertSame('live', SubscriptionState::Live->value);
        $this->assertSame('closed_by_relay', SubscriptionState::ClosedByRelay->value);
        $this->assertSame('closed_by_client', SubscriptionState::ClosedByClient->value);
    }

    public function testFromStringValue(): void
    {
        $this->assertSame(SubscriptionState::Pending, SubscriptionState::from('pending'));
        $this->assertSame(SubscriptionState::Active, SubscriptionState::from('active'));
        $this->assertSame(SubscriptionState::Live, SubscriptionState::from('live'));
        $this->assertSame(SubscriptionState::ClosedByRelay, SubscriptionState::from('closed_by_relay'));
        $this->assertSame(SubscriptionState::ClosedByClient, SubscriptionState::from('closed_by_client'));
    }

    public function testInvalidStringValueReturnsNull(): void
    {
        $this->assertNull(SubscriptionState::tryFrom('invalid'));
    }
}
