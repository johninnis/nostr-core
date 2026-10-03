<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('arbitraryStringProvider')]
    public function testTryFromStringAcceptsAnArbitraryString(string $value): void
    {
        $id = SubscriptionId::tryFromString($value) ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame($value, (string) $id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function arbitraryStringProvider(): iterable
    {
        yield 'printable ascii' => ['sub-1.0_alpha:abc/def'];
        yield 'space' => ['sub id'];
        yield 'newline' => ["sub\nid"];
        yield 'null byte' => ["sub\x00id"];
        yield 'control character' => ["sub\x01id"];
        yield 'delete character' => ["sub\x7Fid"];
        yield 'non-ascii' => ['abonnement-é-日本'];
        yield 'single space' => [' '];
    }

    public function testTryFromStringCountsCharactersNotBytes(): void
    {
        $value = str_repeat('é', 64);

        $id = SubscriptionId::tryFromString($value) ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame($value, (string) $id);
    }

    public function testTryFromStringAcceptsSixtyFourFourByteCharacters(): void
    {
        $value = str_repeat("\u{1F600}", 64);

        $id = SubscriptionId::tryFromString($value) ?? throw new RuntimeException('Expected a valid subscription ID');

        $this->assertSame($value, (string) $id);
    }

    public function testTryFromStringReturnsNullForSixtyFiveMultiByteCharacters(): void
    {
        $this->assertNull(SubscriptionId::tryFromString(str_repeat('é', 65)));
    }

    public function testTryFromStringReturnsNullForInvalidUtf8(): void
    {
        $this->assertNull(SubscriptionId::tryFromString("sub\xFFid"));
    }

    public function testTryFromStringReturnsNullForANonString(): void
    {
        $this->assertNull(SubscriptionId::tryFromString(42));
    }
}
