<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Override;

/**
 * @extends TypedCollection<Challenge>
 */
final class ChallengeCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return Challenge::class;
    }

    public static function fromStrings(mixed $values): self
    {
        return self::fromEach($values, Challenge::tryFromString(...));
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return $this->mapItems(static fn (Challenge $challenge): string => (string) $challenge);
    }
}
