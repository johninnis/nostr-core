<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagFilterTest extends TestCase
{
    public function testFromValuesRefusesAnEmptyTagName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TagFilter::fromValues(['' => ['value']]);
    }

    public function testTryFromValuesRefusesAValueThatIsNotUtf8(): void
    {
        $this->assertNull(TagFilter::tryFromValues(['t' => ["\xff"]]));
    }

    public function testTryFromArrayRefusesANonArray(): void
    {
        $this->assertNull(TagFilter::tryFromArray('#e'));
    }

    public function testFromValuesHoldsAnyNumberOfValuesForOneTagName(): void
    {
        $filter = TagFilter::fromValues(['e' => self::values(1001)]);

        $this->assertCount(1001, $filter->getValues()['e']);
    }

    public function testTryFromArrayParsesAnyNumberOfValuesForOneTagName(): void
    {
        $this->assertCount(1001, TagFilter::tryFromArray(['#e' => self::values(1001)])?->getValues()['e'] ?? []);
    }

    public function testEveryLetterOfTheEnglishAlphabetNamesATagCondition(): void
    {
        $letters = [...range('a', 'z'), ...range('A', 'Z')];
        $wire = array_combine(array_map(static fn (string $letter): string => '#'.$letter, $letters), array_fill(0, count($letters), [str_repeat('a', 64)]));

        $this->assertCount(52, TagFilter::tryFromArray($wire)?->getValues() ?? []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesThatAreNotOneLetter(): iterable
    {
        yield 'two letters' => ['ab'];
        yield 'a letter and a digit' => ['t0'];
        yield 'a digit' => ['1'];
        yield 'a non-English letter' => ['é'];
        yield 'an underscore' => ['_'];
    }

    #[DataProvider('namesThatAreNotOneLetter')]
    public function testTryFromArrayRefusesATagConditionNotNamedByOneLetter(string $name): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#'.$name => ['value']]));
    }

    #[DataProvider('namesThatAreNotOneLetter')]
    public function testFromValuesRefusesATagConditionNotNamedByOneLetter(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        TagFilter::fromValues([$name => ['value']]);
    }

    public function testCanMatchWhenEveryConditionHoldsAValue(): void
    {
        $this->assertTrue(TagFilter::fromValues(['t' => ['value'], 'd' => ['other']])->canMatch());
    }

    public function testCannotMatchWhenAConditionHoldsNoValue(): void
    {
        $this->assertFalse(TagFilter::fromValues(['t' => ['value'], 'd' => []])->canMatch());
    }

    public function testTryFromArrayRejectsNonStringTagValues(): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#t' => ['ok', 42]]));
    }

    public function testTryFromArrayRejectsAnEmptyTagName(): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#' => ['value']]));
    }

    public function testTryFromArrayIgnoresKeysWithoutTheHashPrefix(): void
    {
        $filter = TagFilter::tryFromArray(['ids' => ['not-a-tag-filter'], '#t' => ['value']]);

        $this->assertNotNull($filter);
        $this->assertSame(['t' => ['value']], $filter->getValues());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function referenceConditionsThatAreNotLowercaseHex(): iterable
    {
        yield 'an event id that is not hex' => ['e', 'zz'];
        yield 'an event id in uppercase hex' => ['e', str_repeat('A', 64)];
        yield 'an event id one character short' => ['e', str_repeat('a', 63)];
        yield 'a pubkey that is not hex' => ['p', 'zz'];
        yield 'a pubkey in uppercase hex' => ['p', str_repeat('A', 64)];
        yield 'a pubkey one character too long' => ['p', str_repeat('a', 65)];
    }

    #[DataProvider('referenceConditionsThatAreNotLowercaseHex')]
    public function testTryFromArrayRefusesAReferenceConditionThatIsNotLowercaseHex(string $name, string $value): void
    {
        $this->assertNull(TagFilter::tryFromArray(['#'.$name => [str_repeat('a', 64), $value]]));
    }

    #[DataProvider('referenceConditionsThatAreNotLowercaseHex')]
    public function testFromValuesRefusesAReferenceConditionThatIsNotLowercaseHex(string $name, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('64-character lowercase hex');

        TagFilter::fromValues([$name => [$value]]);
    }

    public function testTryFromArrayAcceptsLowercaseHexReferenceConditions(): void
    {
        $values = ['e' => [str_repeat('a', 64)], 'p' => [str_repeat('b', 64)]];

        $this->assertSame($values, TagFilter::tryFromArray(['#e' => $values['e'], '#p' => $values['p']])?->getValues());
    }

    public function testTryFromArrayLeavesUppercaseReferenceLettersUnconstrained(): void
    {
        $this->assertNotNull(TagFilter::tryFromArray(['#E' => ['zz'], '#P' => ['zz']]));
    }

    /**
     * @return list<string>
     */
    private static function values(int $count): array
    {
        return array_map(static fn (int $index): string => str_pad(dechex($index), 64, '0', STR_PAD_LEFT), range(1, $count));
    }
}
