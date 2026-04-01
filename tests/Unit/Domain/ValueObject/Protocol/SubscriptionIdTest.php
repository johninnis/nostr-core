<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SubscriptionIdTest extends TestCase
{
    public function testFromStringCreatesValidInstance(): void
    {
        $id = SubscriptionId::fromString('my-sub-id');

        $this->assertSame('my-sub-id', (string) $id);
        $this->assertSame('my-sub-id', (string) $id);
    }

    public function testConstructorThrowsForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription ID cannot be empty');

        new SubscriptionId('');
    }

    public function testConstructorThrowsForStringExceeding64Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription ID cannot exceed 64 characters');

        new SubscriptionId(str_repeat('a', 65));
    }

    public function testConstructorAllows64CharacterString(): void
    {
        $id = new SubscriptionId(str_repeat('a', 64));

        $this->assertSame(64, strlen((string) $id));
    }

    public function testGenerateCreatesValidId(): void
    {
        $id = SubscriptionId::generate();

        $this->assertSame(32, strlen((string) $id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $id);
    }

    public function testShortCreatesValidId(): void
    {
        $id = SubscriptionId::short();

        $this->assertSame(8, strlen((string) $id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', (string) $id);
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $id1 = SubscriptionId::fromString('test-id');
        $id2 = SubscriptionId::fromString('test-id');

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $id1 = SubscriptionId::fromString('test-id-1');
        $id2 = SubscriptionId::fromString('test-id-2');

        $this->assertFalse($id1->equals($id2));
    }

    public function testGenerateProducesUniqueIds(): void
    {
        $id1 = SubscriptionId::generate();
        $id2 = SubscriptionId::generate();

        $this->assertFalse($id1->equals($id2));
    }
}
