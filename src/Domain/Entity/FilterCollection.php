<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\TypedCollection;
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

    /**
     * @return list<Filter>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public static function empty(): self
    {
        return new self();
    }
}
