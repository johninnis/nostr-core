<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Override;

/**
 * @extends TypedCollection<RelayReference>
 */
final class RelayReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return RelayReference::class;
    }

    private static function tryParse(mixed $value): ?RelayReference
    {
        return is_array($value) ? RelayReference::fromArray($value) : null;
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
        return $this->mapItems(static fn (RelayReference $reference): array => $reference->toArray());
    }
}
