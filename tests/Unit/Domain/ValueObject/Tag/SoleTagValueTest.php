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
}
