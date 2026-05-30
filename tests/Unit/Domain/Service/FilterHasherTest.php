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

    // Pinned digests of the canonical form, asserted identically in the TS suite — the
    // cross-language conformance anchors. Equivalent inputs must hash to these exact digests in
    // both runtimes. (The property tests above use language-local inputs, so conformance rides
    // on these shared anchors.)
    public function testEmptyFilterSetHashesToSha256OfEmptyArray(): void
    {
        $this->assertSame('4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945', FilterHasher::hash());
    }

    public function testKindsAndLimitFilterMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'a34519033f2032b87a019ef94f4be40fc1ab6a621d2b66c55b0d386c3e576587',
            FilterHasher::hash(new Filter(kinds: [2, 1], limit: 5)),
        );
    }

    public function testSingleEmptyFilterMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'e10808d43975dc400731053386849f864f297e6c4f7519c380f3dbaf7067a840',
            FilterHasher::hash(new Filter()),
        );
    }
}
