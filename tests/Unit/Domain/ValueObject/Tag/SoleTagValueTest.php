<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;
use Innis\Nostr\Core\Domain\ValueObject\Tag\SoleTagValue;
use PHPUnit\Framework\TestCase;

final class SoleTagValueTest extends TestCase
{
    public function testNoValuesAreAbsent(): void
    {
        $this->assertSame(SoleTagValueState::Absent, SoleTagValue::fromValues([])->getState());
    }

    public function testIdenticalRepeatsAreOneClaim(): void
    {
        $this->assertSame('a', SoleTagValue::fromValues(['a', 'a'])->getValue());
    }

    public function testDifferentValuesDisagree(): void
    {
        $this->assertSame(SoleTagValueState::Disagreeing, SoleTagValue::fromValues(['a', 'b', 'a'])->getState());
    }

    public function testNumericallyEqualSpellingsDisagree(): void
    {
        $this->assertSame(SoleTagValueState::Disagreeing, SoleTagValue::fromValues(['1', '01'])->getState());
    }

    public function testDisagreeingValuesHaveNoValue(): void
    {
        $this->assertNull(SoleTagValue::fromValues(['a', 'b'])->getValue());
    }

    public function testDisagreeingValuesNameTheDistinctValuesInTheOrderFirstSeen(): void
    {
        $this->assertSame(['b', 'a'], SoleTagValue::fromValues(['b', 'a', 'b'])->getDistinctValues());
    }

    public function testOneClaimNamesItsValueOnce(): void
    {
        $this->assertSame(['a'], SoleTagValue::fromValues(['a', 'a'])->getDistinctValues());
    }

    public function testNoValuesNameNone(): void
    {
        $this->assertSame([], SoleTagValue::fromValues([])->getDistinctValues());
    }

    public function testValuesThatCanonicaliseAlikeAreOneClaim(): void
    {
        $this->assertSame('upload', SoleTagValue::fromValues(['upload', 'UPLOAD'], strtolower(...))->getValue());
    }

    public function testCanonicalValuesThatDifferDisagree(): void
    {
        $this->assertSame(['upload', 'delete'], SoleTagValue::fromValues(['Upload', 'DELETE'], strtolower(...))->getDistinctValues());
    }

    public function testAValueWithNoCanonicalFormTakesNoPart(): void
    {
        $this->assertSame('1', SoleTagValue::fromValues(['1', 'x'], static fn (string $value): ?string => ctype_digit($value) ? $value : null)->getValue());
    }

    public function testValuesNoneOfWhichHasACanonicalFormAreAbsent(): void
    {
        $this->assertSame(SoleTagValueState::Absent, SoleTagValue::fromValues(['x'], static fn (string $value): ?string => null)->getState());
    }
}
