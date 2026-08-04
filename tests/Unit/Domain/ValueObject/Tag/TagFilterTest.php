<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TagFilterTest extends TestCase
{
    public function testFromValuesRejectsMoreThanTheCapForOneTagName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may contain at most');

        TagFilter::fromValues(['e' => self::values(Filter::MAX_VALUES_PER_FIELD + 1)]);
    }

    public function testFromValuesAcceptsExactlyTheCap(): void
    {
        $filter = TagFilter::fromValues(['e' => self::values(Filter::MAX_VALUES_PER_FIELD)]);

        $this->assertCount(Filter::MAX_VALUES_PER_FIELD, $filter->getValues()['e']);
    }

    public function testTryFromArrayRejectsMoreThanTheCapForOneTagName(): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#e' => self::values(Filter::MAX_VALUES_PER_FIELD + 1)]));
    }

    public function testTryFromArrayAcceptsExactlyTheCap(): void
    {
        $this->assertNotNull(TagFilter::tryFromArray(['#e' => self::values(Filter::MAX_VALUES_PER_FIELD)]));
    }

    // Deliberate: the cap is per tag name and the number of distinct names is intentionally unbounded — NIP-01 places no limit on which names a client may query, so bounding the total is the transport's and the relay's resource policy, not this type's; see the relay-protocol parsing caps in SECURITY.md
    public function testTheNumberOfDistinctTagNamesIsNotCapped(): void
    {
        $wire = [];
        for ($i = 0; $i < 2000; ++$i) {
            $wire['#t'.$i] = ['value'];
        }

        $filter = TagFilter::tryFromArray($wire);

        $this->assertNotNull($filter);
        $this->assertCount(2000, $filter->getValues());
    }

    public function testTryFromArrayRejectsNonStringTagValues(): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#e' => ['ok', 42]]));
    }

    public function testTryFromArrayRejectsAnEmptyTagName(): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#' => ['value']]));
    }

    public function testTryFromArrayIgnoresKeysWithoutTheHashPrefix(): void
    {
        $filter = TagFilter::tryFromArray(['ids' => ['not-a-tag-filter'], '#e' => ['value']]);

        $this->assertNotNull($filter);
        $this->assertSame(['e' => ['value']], $filter->getValues());
    }

    /**
     * @return list<string>
     */
    private static function values(int $count): array
    {
        return array_map(static fn (int $index): string => 'v'.$index, range(1, $count));
    }
}
