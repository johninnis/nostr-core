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

    public static function empty(): self
    {
        return new self();
    }

    public function toJsonArray(): array
    {
        return array_map(static fn (Filter $filter) => $filter->toArray(), $this->items);
    }
}
