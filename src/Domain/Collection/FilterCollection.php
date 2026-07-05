<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Override;
use stdClass;

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

    public function matches(Event $event): bool
    {
        return array_any($this->items, static fn (Filter $filter): bool => $filter->matches($event));
    }

    public static function tryFromArray(mixed $values): ?self
    {
        return self::tryFromEach($values, Filter::tryFromArray(...));
    }

    /**
     * @return list<array<string, mixed>|stdClass>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (Filter $filter): array|stdClass => $filter->jsonSerialize());
    }
}
