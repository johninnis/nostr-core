<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Sha256Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Sha256HashTest extends TestCase
{
    private const string EMPTY_CONTENT_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public function testOfContentIsTheContentsSha256AsLowercaseHex(): void
    {
        $this->assertSame(hash('sha256', 'body'), (string) Sha256Hash::ofContent('body'));
    }

    public function testTryFromHexReadsALowercaseHash(): void
    {
        $this->assertSame(self::EMPTY_CONTENT_HASH, (string) Sha256Hash::tryFromHex(self::EMPTY_CONTENT_HASH));
    }

    #[DataProvider('notASha256Hash')]
    public function testTryFromHexRefusesWhatIsNotLowercaseSha256Hex(string $hex): void
    {
        $this->assertNull(Sha256Hash::tryFromHex($hex));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notASha256Hash(): iterable
    {
        yield 'upper case' => [strtoupper(self::EMPTY_CONTENT_HASH)];
        yield 'too short' => [substr(self::EMPTY_CONTENT_HASH, 0, 62)];
        yield 'too long' => [self::EMPTY_CONTENT_HASH.'00'];
        yield 'not hex' => [str_repeat('g', 64)];
        yield 'empty' => [''];
    }

    public function testFromHexRefusesWhatIsNotLowercaseSha256Hex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Sha256Hash::fromHex(strtoupper(self::EMPTY_CONTENT_HASH));
    }

    public function testTheEmptyContentsHashIsOfEmptyContent(): void
    {
        $this->assertTrue(Sha256Hash::fromHex(self::EMPTY_CONTENT_HASH)->isOfEmptyContent());
    }

    public function testAnyOtherHashIsNotOfEmptyContent(): void
    {
        $this->assertFalse(Sha256Hash::ofContent('body')->isOfEmptyContent());
    }

    public function testHashesOfTheSameContentAreEqual(): void
    {
        $this->assertTrue(Sha256Hash::ofContent('body')->equals(Sha256Hash::fromHex(hash('sha256', 'body'))));
    }

    public function testHashesOfDifferentContentAreNotEqual(): void
    {
        $this->assertFalse(Sha256Hash::ofContent('body')->equals(Sha256Hash::ofContent('other')));
    }
}
