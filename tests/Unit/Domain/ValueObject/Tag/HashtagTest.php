<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashtagTest extends TestCase
{
    public function testAHashtagIsLowercasedOnConstruction(): void
    {
        $this->assertSame('nostr', (string) Hashtag::fromString('NoStR'));
    }

    public function testLowercasingIsUnicodeAware(): void
    {
        $this->assertSame('äöü', (string) Hashtag::fromString('ÄÖÜ'));
    }

    public function testTryFromStringRefusesAnEmptyValue(): void
    {
        $this->assertNull(Hashtag::tryFromString(''));
    }

    public function testTryFromStringRefusesANonString(): void
    {
        $this->assertNull(Hashtag::tryFromString(42));
    }

    public function testFromStringRefusesAnEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A hashtag cannot be empty');

        Hashtag::fromString('');
    }

    public function testALowercaseValueIsKeptAsIs(): void
    {
        $this->assertSame('bitcoin', (string) Hashtag::fromString('bitcoin'));
    }

    public function testALeadingHashIsNotStripped(): void
    {
        $this->assertSame('#nostr', (string) Hashtag::fromString('#nostr'));
    }

    public function testHashtagsDifferingOnlyInCaseAreEqual(): void
    {
        $this->assertTrue(Hashtag::fromString('Nostr')->equals(Hashtag::fromString('nostr')));
    }

    public function testDifferentHashtagsAreNotEqual(): void
    {
        $this->assertFalse(Hashtag::fromString('nostr')->equals(Hashtag::fromString('bitcoin')));
    }
}
