<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Reference\QuoteAnalysis;
use PHPUnit\Framework\TestCase;

final class QuoteAnalysisTest extends TestCase
{
    public function testExposesEachConstructorFlag(): void
    {
        $analysis = new QuoteAnalysis(hasQuoteTag: true, hasEventInContent: true, isRepost: false, isQuote: true);

        $this->assertTrue($analysis->hasQuoteTag());
        $this->assertTrue($analysis->hasEventInContent());
        $this->assertFalse($analysis->isRepost());
        $this->assertTrue($analysis->isQuote());
    }

    public function testFlagsDefaultToFalseFromEmptyArray(): void
    {
        $analysis = QuoteAnalysis::fromArray([]);

        $this->assertFalse($analysis->hasQuoteTag());
        $this->assertFalse($analysis->hasEventInContent());
        $this->assertFalse($analysis->isRepost());
        $this->assertFalse($analysis->isQuote());
    }

    public function testRoundTripsThroughArray(): void
    {
        $analysis = new QuoteAnalysis(hasQuoteTag: true, hasEventInContent: false, isRepost: true, isQuote: false);

        $restored = QuoteAnalysis::fromArray($analysis->toArray());

        $this->assertTrue($restored->hasQuoteTag());
        $this->assertFalse($restored->hasEventInContent());
        $this->assertTrue($restored->isRepost());
        $this->assertFalse($restored->isQuote());
    }
}
