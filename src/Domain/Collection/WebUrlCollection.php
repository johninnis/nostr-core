<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\WebUrl;
use Override;

/**
 * @extends KeyedCollection<WebUrl>
 */
final class WebUrlCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return WebUrl::class;
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, WebUrl::tryFromString(...))->unique();
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return $this->mapItems(static fn (WebUrl $url): string => (string) $url);
    }
}
