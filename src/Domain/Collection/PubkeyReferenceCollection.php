<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Override;

/**
 * @extends TypedCollection<PubkeyReference>
 */
final class PubkeyReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return PubkeyReference::class;
    }

    private static function tryParse(mixed $value): ?PubkeyReference
    {
        return is_array($value) ? PubkeyReference::fromArray($value) : null;
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
        return $this->mapItems(static fn (PubkeyReference $reference): array => $reference->toArray());
    }
}
