<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionIdTest extends TestCase
{
    public function testTryFromStringCreatesValidInstance(): void
    {
        $id = SubscriptionId::tryFromString('my-sub-id') ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame('my-sub-id', (string) $id);
    }

    public function testTryFromStringReturnsNullForEmptyString(): void
    {
        $this->assertNull(SubscriptionId::tryFromString(''));
    }

    public function testTryFromStringReturnsNullForStringExceeding64Characters(): void
    {
        $this->assertNull(SubscriptionId::tryFromString(str_repeat('a', 65)));
    }

    public function testTryFromStringAllows64CharacterString(): void
    {
        $id = SubscriptionId::tryFromString(str_repeat('a', 64)) ?? throw new RuntimeException('Expected a valid subscription ID');

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
        $id1 = SubscriptionId::tryFromString('test-id') ?? throw new RuntimeException('Expected a valid subscription ID');
        $id2 = SubscriptionId::tryFromString('test-id');

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $id1 = SubscriptionId::tryFromString('test-id-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $id2 = SubscriptionId::tryFromString('test-id-2') ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertFalse($id1->equals($id2));
    }

    public function testGenerateProducesUniqueIds(): void
    {
        $id1 = SubscriptionId::generate();
        $id2 = SubscriptionId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testTryFromStringReturnsNullForNullByte(): void
    {
        $this->assertNull(SubscriptionId::tryFromString("sub\x00id"));
    }

    public function testTryFromStringReturnsNullForNewline(): void
    {
        $this->assertNull(SubscriptionId::tryFromString("sub\nid"));
    }

    public function testTryFromStringReturnsNullForTrailingNewline(): void
    {
        $this->assertNull(SubscriptionId::tryFromString("subid\n"));
    }

    public function testTryFromStringReturnsNullForControlCharacter(): void
    {
        $this->assertNull(SubscriptionId::tryFromString("sub\x01id"));
    }

    public function testTryFromStringReturnsNullForSpace(): void
    {
        $this->assertNull(SubscriptionId::tryFromString('sub id'));
    }

    public function testTryFromStringReturnsNullForDelCharacter(): void
    {
        $this->assertNull(SubscriptionId::tryFromString("sub\x7Fid"));
    }

    public function testTryFromStringAcceptsPrintableAsciiRange(): void
    {
        $id = SubscriptionId::tryFromString('sub-1.0_alpha:abc/def') ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame('sub-1.0_alpha:abc/def', (string) $id);
    }
}
