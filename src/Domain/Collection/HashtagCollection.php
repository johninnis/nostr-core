<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Override;

/**
 * @extends KeyedCollection<Hashtag>
 */
final class HashtagCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Hashtag::class;
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, Hashtag::tryFromString(...));
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return $this->mapItems(static fn (Hashtag $hashtag): string => (string) $hashtag);
    }

    public function equals(self $other): bool
    {
        return $this->toStrings() === $other->toStrings();
    }
}
