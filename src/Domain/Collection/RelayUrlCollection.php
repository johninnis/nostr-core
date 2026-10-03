<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Override;

/**
 * @extends KeyedCollection<RelayUrl>
 */
final class RelayUrlCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return RelayUrl::class;
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, RelayUrl::tryFromString(...))->unique();
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return $this->mapItems(static fn (RelayUrl $relayUrl): string => (string) $relayUrl);
    }
}
