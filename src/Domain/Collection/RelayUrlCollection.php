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

    public static function fromStrings(mixed $values): self
    {
        return new self(self::parseStrings($values, RelayUrl::fromString(...)));
    }

    public function unique(): self
    {
        return new self($this->deduplicate(static fn (RelayUrl $relayUrl): string => (string) $relayUrl));
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return $this->mapItems(static fn (RelayUrl $relayUrl): string => (string) $relayUrl);
    }
}
