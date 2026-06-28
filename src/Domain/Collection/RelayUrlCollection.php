<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Override;

/**
 * @extends TypedCollection<RelayUrl>
 */
final class RelayUrlCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return RelayUrl::class;
    }

    private static function keyOf(RelayUrl $relayUrl): string
    {
        return (string) $relayUrl;
    }

    public static function fromStrings(mixed $values): self
    {
        return new self(self::parseStrings($values, RelayUrl::fromString(...)));
    }

    public function unique(): self
    {
        return new self($this->deduplicate(self::keyOf(...)));
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return $this->mapItems(self::keyOf(...));
    }
}
