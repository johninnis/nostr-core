<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChallengeTest extends TestCase
{
    public function testAChallengeKeepsItsValueVerbatim(): void
    {
        $challenge = Challenge::tryFromString('Ab3-XYZ_') ?? throw new RuntimeException('Expected a challenge');

        $this->assertSame('Ab3-XYZ_', (string) $challenge);
    }

    public function testTryFromStringRefusesAnEmptyChallenge(): void
    {
        $this->assertNull(Challenge::tryFromString(''));
    }

    public function testTryFromStringRefusesANonString(): void
    {
        $this->assertNull(Challenge::tryFromString(42));
    }

    public function testFromStringRefusesAnEmptyChallenge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An AUTH challenge cannot be empty');

        Challenge::fromString('');
    }

    public function testTwoChallengesWithTheSameValueAreEqual(): void
    {
        $this->assertTrue(Challenge::fromString('same')->equals(Challenge::fromString('same')));
    }

    public function testChallengesWithDifferentValuesAreNotEqual(): void
    {
        $this->assertFalse(Challenge::fromString('one')->equals(Challenge::fromString('two')));
    }

    public function testComparisonIsCaseSensitive(): void
    {
        $this->assertFalse(Challenge::fromString('abc')->equals(Challenge::fromString('ABC')));
    }

    public function testAChallengeIsNotEqualToAPrefixOfItself(): void
    {
        $this->assertFalse(Challenge::fromString('abcdef')->equals(Challenge::fromString('abc')));
    }
}
