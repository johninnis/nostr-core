<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Enum\SubscriptionState;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('stateStringValues')]
    public function testBackedStringValue(SubscriptionState $state, string $value): void
    {
        $this->assertSame($value, $state->value);
    }

    #[DataProvider('stateStringValues')]
    public function testFromParsesStringValue(SubscriptionState $state, string $value): void
    {
        $this->assertSame($state, SubscriptionState::from($value));
    }

    /**
     * @return iterable<string, array{SubscriptionState, string}>
     */
    public static function stateStringValues(): iterable
    {
        yield 'pending' => [SubscriptionState::Pending, 'pending'];
        yield 'active' => [SubscriptionState::Active, 'active'];
        yield 'live' => [SubscriptionState::Live, 'live'];
        yield 'closed by relay' => [SubscriptionState::ClosedByRelay, 'closed_by_relay'];
        yield 'closed by client' => [SubscriptionState::ClosedByClient, 'closed_by_client'];
    }

    #[DataProvider('invalidStringValues')]
    public function testInvalidStringValueReturnsNull(string $value): void
    {
        $this->assertNull(SubscriptionState::tryFrom($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidStringValues(): iterable
    {
        yield 'unknown word' => ['invalid'];
        yield 'empty string' => [''];
        yield 'wrong case' => ['Pending'];
    }
}
