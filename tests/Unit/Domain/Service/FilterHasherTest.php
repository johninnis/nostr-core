<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Service\FilterHasher;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use PHPUnit\Framework\TestCase;

final class FilterHasherTest extends TestCase
{
    public function testHashIsStableForTheSameInput(): void
    {
        $filter = Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]), kinds: EventKindCollection::fromInts([1, 2]));

        $this->assertSame(FilterHasher::hash($filter), FilterHasher::hash($filter));
    }

    public function testHashIsIndependentOfArrayElementOrder(): void
    {
        $this->assertSame(
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64), str_repeat('b', 64)]))),
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('b', 64), str_repeat('a', 64)]))),
        );
    }

    public function testHashIsIndependentOfTagValueOrder(): void
    {
        $this->assertSame(
            FilterHasher::hash(Filter::from(tags: TagFilter::fromValues(['t' => ['b', 'a']]))),
            FilterHasher::hash(Filter::from(tags: TagFilter::fromValues(['t' => ['a', 'b']]))),
        );
    }

    public function testHashIsIndependentOfTheOrderOfFiltersInTheSet(): void
    {
        $first = Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]));
        $second = Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('b', 64)]));

        $this->assertSame(
            FilterHasher::hash($first, $second),
            FilterHasher::hash($second, $first),
        );
    }

    public function testHashDistinguishesFiltersThatSelectDifferentEvents(): void
    {
        $this->assertNotSame(
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]))),
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('b', 64)]))),
        );
    }

    public function testHashTreatsAbsentFieldsConsistently(): void
    {
        $this->assertNotSame(
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]))),
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]), limit: 10)),
        );
    }

    public function testHashIsLowercaseHexSha256(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]))));
    }

    public function testHashPreservesDuplicateArrayElements(): void
    {
        $this->assertNotSame(
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64), str_repeat('a', 64)]))),
            FilterHasher::hash(Filter::from(authors: PublicKeyCollection::fromHexValues([str_repeat('a', 64)]))),
        );
    }

    public function testEmptyFilterSetHashesToSha256OfEmptyArray(): void
    {
        $this->assertSame('4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945', FilterHasher::hash());
    }

    public function testKindsAndLimitFilterMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'a34519033f2032b87a019ef94f4be40fc1ab6a621d2b66c55b0d386c3e576587',
            FilterHasher::hash(Filter::from(kinds: EventKindCollection::fromInts([2, 1]), limit: 5)),
        );
    }

    public function testSingleEmptyFilterMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'e10808d43975dc400731053386849f864f297e6c4f7519c380f3dbaf7067a840',
            FilterHasher::hash(Filter::from()),
        );
    }

    public function testU2028SearchStringMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'aee96085e5802e7b70a145ffdf6aa7e2335469aa223be66c79c9ad1699ecd7f2',
            FilterHasher::hash(Filter::from(search: "\u{2028}")),
        );
    }

    public function testAstralSearchCharacterMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'ac283a84cb87cd19a956f552a82cb9155fc1a980d576356c4d987e71710a4dd3',
            FilterHasher::hash(Filter::from(search: "\u{1F600}")),
        );
    }

    public function testAstralTagValueSortMatchesTheCrossLanguageAnchor(): void
    {
        $this->assertSame(
            'a47382ebe89a655c3d9d1e27a1e5e445ca0dd4f5348e72f518b2a98b6f77f92b',
            FilterHasher::hash(Filter::from(tags: TagFilter::fromValues(['t' => ["\u{1F600}", "\u{1F4A9}"]]))),
        );
    }
}
