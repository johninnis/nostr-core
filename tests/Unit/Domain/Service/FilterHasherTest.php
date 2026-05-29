<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Service\FilterHasher;
use PHPUnit\Framework\TestCase;

final class FilterHasherTest extends TestCase
{
    public function testHashIsStableForTheSameInput(): void
    {
        $filter = new Filter(authors: ['a'], kinds: [1, 2]);

        $this->assertSame(FilterHasher::hash($filter), FilterHasher::hash($filter));
    }

    public function testHashIsIndependentOfArrayElementOrder(): void
    {
        $this->assertSame(
            FilterHasher::hash(new Filter(authors: ['a', 'b'])),
            FilterHasher::hash(new Filter(authors: ['b', 'a'])),
        );
    }

    public function testHashIsIndependentOfTagValueOrder(): void
    {
        $this->assertSame(
            FilterHasher::hash(new Filter(tags: ['t' => ['b', 'a']])),
            FilterHasher::hash(new Filter(tags: ['t' => ['a', 'b']])),
        );
    }

    public function testHashIsIndependentOfTheOrderOfFiltersInTheSet(): void
    {
        $first = new Filter(authors: ['a']);
        $second = new Filter(authors: ['b']);

        $this->assertSame(
            FilterHasher::hash($first, $second),
            FilterHasher::hash($second, $first),
        );
    }

    public function testHashDistinguishesFiltersThatSelectDifferentEvents(): void
    {
        $this->assertNotSame(
            FilterHasher::hash(new Filter(authors: ['a'])),
            FilterHasher::hash(new Filter(authors: ['b'])),
        );
    }

    public function testHashTreatsAbsentFieldsConsistently(): void
    {
        $this->assertNotSame(
            FilterHasher::hash(new Filter(authors: ['a'])),
            FilterHasher::hash(new Filter(authors: ['a'], limit: 10)),
        );
    }

    public function testHashIsLowercaseHexSha256(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', FilterHasher::hash(new Filter(authors: ['a'])));
    }

    public function testHashPreservesDuplicateArrayElements(): void
    {
        $this->assertNotSame(
            FilterHasher::hash(new Filter(authors: ['a', 'a'])),
            FilterHasher::hash(new Filter(authors: ['a'])),
        );
    }

    // Pinned digests of the canonical form. These are the cross-language conformance anchors:
    // TS `hashFilters` produces the same digest for the same canonical input.
    public function testEmptyFilterSetHashesToSha256OfEmptyArray(): void
    {
        $this->assertSame('4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945', FilterHasher::hash());
    }

    public function testDuplicateAuthorsMatchTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'cd6efb326b2adca65cf8d1b26990865205c43ad50269b16562cf1ef1c8598796',
            FilterHasher::hash(new Filter(authors: ['a', 'a'])),
        );
    }
}
