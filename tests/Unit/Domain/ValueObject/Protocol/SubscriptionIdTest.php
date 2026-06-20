<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionIdTest extends TestCase
{
    public function testFromStringCreatesValidInstance(): void
    {
        $id = SubscriptionId::fromString('my-sub-id') ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame('my-sub-id', (string) $id);
    }

    public function testFromStringReturnsNullForEmptyString(): void
    {
        $this->assertNull(SubscriptionId::fromString(''));
    }

    public function testFromStringReturnsNullForStringExceeding64Characters(): void
    {
        $this->assertNull(SubscriptionId::fromString(str_repeat('a', 65)));
    }

    public function testFromStringAllows64CharacterString(): void
    {
        $id = SubscriptionId::fromString(str_repeat('a', 64)) ?? throw new RuntimeException('Expected a valid subscription ID');

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
        $id1 = SubscriptionId::fromString('test-id') ?? throw new RuntimeException('Expected a valid subscription ID');
        $id2 = SubscriptionId::fromString('test-id');

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $id1 = SubscriptionId::fromString('test-id-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $id2 = SubscriptionId::fromString('test-id-2') ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertFalse($id1->equals($id2));
    }

    public function testGenerateProducesUniqueIds(): void
    {
        $id1 = SubscriptionId::generate();
        $id2 = SubscriptionId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testFromStringReturnsNullForNullByte(): void
    {
        $this->assertNull(SubscriptionId::fromString("sub\x00id"));
    }

    public function testFromStringReturnsNullForNewline(): void
    {
        $this->assertNull(SubscriptionId::fromString("sub\nid"));
    }

    public function testFromStringReturnsNullForControlCharacter(): void
    {
        $this->assertNull(SubscriptionId::fromString("sub\x01id"));
    }

    public function testFromStringReturnsNullForSpace(): void
    {
        $this->assertNull(SubscriptionId::fromString('sub id'));
    }

    public function testFromStringReturnsNullForDelCharacter(): void
    {
        $this->assertNull(SubscriptionId::fromString("sub\x7Fid"));
    }

    public function testFromStringAcceptsPrintableAsciiRange(): void
    {
        $id = SubscriptionId::fromString('sub-1.0_alpha:abc/def') ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame('sub-1.0_alpha:abc/def', (string) $id);
    }
}
