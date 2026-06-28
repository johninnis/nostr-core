<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Override;

/**
 * @extends TypedCollection<Filter>
 */
final class FilterCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Filter::class;
    }

    private static function tryParse(mixed $value): ?Filter
    {
        return is_array($value) ? Filter::fromArray($value) : null;
    }

    public static function fromWire(mixed $values): ?self
    {
        $filters = self::parseEachStrict($values, self::tryParse(...));

        return null === $filters ? null : new self($filters);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (Filter $filter): array => $filter->toArray());
    }
}
