<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Override;

/**
 * @extends TypedCollection<Hashtag>
 */
final class HashtagCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Hashtag::class;
    }

    private static function keyOf(Hashtag $hashtag): string
    {
        return (string) $hashtag;
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, Hashtag::tryFromString(...));
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

    public function equals(self $other): bool
    {
        return $this->toStrings() === $other->toStrings();
    }

    public function contains(Hashtag $hashtag): bool
    {
        return $this->containsByKey(self::keyOf($hashtag), self::keyOf(...));
    }

    public function intersect(self $other): self
    {
        return new self($this->retainByKey($other, self::keyOf(...), true));
    }

    public function diff(self $other): self
    {
        return new self($this->retainByKey($other, self::keyOf(...), false));
    }
}
