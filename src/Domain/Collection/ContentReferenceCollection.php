<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Override;

/**
 * @extends TypedCollection<ContentReference>
 */
final class ContentReferenceCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return ContentReference::class;
    }

    public static function fromArrays(mixed $values): self
    {
        return new self(self::parseArrays($values, ContentReference::fromArray(...)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toJsonArray(): array
    {
        return $this->mapItems(static fn (ContentReference $reference): array => $reference->toArray());
    }
}
