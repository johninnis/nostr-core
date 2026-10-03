<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Reference\QuoteAnalysis;
use PHPUnit\Framework\TestCase;

final class QuoteAnalysisTest extends TestCase
{
    public function testExposesEachConstructorFlag(): void
    {
        $analysis = new QuoteAnalysis(hasQuoteTag: true, hasQuoteInContent: true, isRepost: false, isShortNote: true);

        $this->assertTrue($analysis->hasQuoteTag());
        $this->assertTrue($analysis->hasQuoteInContent());
        $this->assertFalse($analysis->isRepost());
        $this->assertTrue($analysis->isShortNote());
    }

    public function testAQuoteTagMakesAQuote(): void
    {
        $this->assertTrue(new QuoteAnalysis(hasQuoteTag: true, hasQuoteInContent: false, isRepost: false, isShortNote: false)->isQuote());
    }

    public function testAShortNoteNamingAnEventInItsContentIsAQuote(): void
    {
        $this->assertTrue(new QuoteAnalysis(hasQuoteTag: false, hasQuoteInContent: true, isRepost: false, isShortNote: true)->isQuote());
    }

    public function testAnotherKindNamingAnEventInItsContentIsNotAQuote(): void
    {
        $this->assertFalse(new QuoteAnalysis(hasQuoteTag: false, hasQuoteInContent: true, isRepost: false, isShortNote: false)->isQuote());
    }

    public function testAStoredQuoteFlagWithNothingQuotedIsNotAQuote(): void
    {
        $this->assertFalse(QuoteAnalysis::fromArray(['is_quote' => true])->isQuote());
    }

    public function testFlagsDefaultToFalseFromEmptyArray(): void
    {
        $analysis = QuoteAnalysis::fromArray([]);

        $this->assertFalse($analysis->hasQuoteTag());
        $this->assertFalse($analysis->hasQuoteInContent());
        $this->assertFalse($analysis->isRepost());
        $this->assertFalse($analysis->isShortNote());
    }

    public function testRoundTripsThroughArray(): void
    {
        $analysis = new QuoteAnalysis(hasQuoteTag: true, hasQuoteInContent: false, isRepost: true, isShortNote: false);

        $this->assertSame($analysis->toArray(), QuoteAnalysis::fromArray($analysis->toArray())->toArray());
    }
}
