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

    private static function tryParse(mixed $value): ?RelayUrl
    {
        return is_string($value) ? RelayUrl::fromString($value) : null;
    }

    public static function fromStrings(mixed $values): self
    {
        return new self(self::parseEach($values, self::tryParse(...)));
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
