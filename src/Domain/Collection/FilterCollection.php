<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\Entity\Filter;
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

    public static function fromWire(mixed $values): ?self
    {
        $filters = self::parseAll($values, static fn (mixed $value): ?Filter => is_array($value) ? Filter::fromArray($value) : null);

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
