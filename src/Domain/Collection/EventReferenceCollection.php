<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Override;

/**
 * @extends TypedCollection<EventReference>
 */
final class EventReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return EventReference::class;
    }

    private static function tryParse(mixed $value): ?EventReference
    {
        return is_array($value) ? EventReference::fromArray($value) : null;
    }

    public static function fromArrays(mixed $values): self
    {
        return new self(self::parseEach($values, self::tryParse(...)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (EventReference $reference): array => $reference->toArray());
    }
}
